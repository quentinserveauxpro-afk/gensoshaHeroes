<?php

namespace App\Infirmerie;

use App\Entity\SejourInfirmerie;
use App\Enum\StatutSejour;
use App\Geo\DistanceCalculator;
use App\Repository\SejourInfirmerieRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * État de l'infirmerie pour la page et pour le flux temps réel (une requête pour tous les séjours).
 *
 * Entre deux passages du worker, santé et fatigue sont projetées à l'instant de la requête
 * avec les taux enregistrés (calcul pur, sans écriture), comme les positions des héros en
 * déplacement. Les transitions (arrivée, rétablissement, sortie) restent décidées par
 * app:infirmary:update : le navigateur n'en décide jamais.
 */
final class InfirmerieEtatProvider
{
    public function __construct(
        private readonly SejourInfirmerieRepository $sejours,
        private readonly HeroRecoveryService $recuperation,
        private readonly HeroMedicalEvaluationService $medical,
        private readonly InfirmerieConfiguration $config,
        private readonly QgConfiguration $qg,
        private readonly DistanceCalculator $distances,
        private readonly UrlGeneratorInterface $urls,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function instantane(?\DateTimeImmutable $now = null, ?array $sejours = null): array
    {
        $now ??= $this->clock->now();
        $sejours ??= $this->sejours->findActifs();

        return [
            'indicateurs' => $this->indicateurs($sejours),
            'sejours' => array_map(fn (SejourInfirmerie $s) => $this->sejour($s, $now), $sejours),
            'qg' => $this->qg->versTableau(),
        ];
    }

    /**
     * @param list<SejourInfirmerie> $sejours séjours actifs
     *
     * @return array<string, int|null>
     */
    public function indicateurs(array $sejours): array
    {
        $compte = static fn (callable $f) => \count(array_filter($sejours, $f));
        $occupes = \count($sejours);

        return [
            'retours' => $compte(static fn (SejourInfirmerie $s) => $s->getStatut()->isRetour()),
            'traitements' => $compte(static fn (SejourInfirmerie $s) => StatutSejour::EN_TRAITEMENT === $s->getStatut()),
            'retablis' => $compte(static fn (SejourInfirmerie $s) => StatutSejour::RETABLI === $s->getStatut()),
            'capacite' => $this->config->capacite,
            'occupes' => $occupes,
            'litsDisponibles' => max(0, $this->config->capacite - $occupes),
            'dureeMoyenne' => $this->sejours->dureeMoyenneTraitement(),
        ];
    }

    /** @return array<string, mixed> */
    public function sejour(SejourInfirmerie $sejour, \DateTimeImmutable $now): array
    {
        $hero = $sejour->getHero();
        $max = max(0, $hero->getPointsDeVieMaximum());
        [$sante, $fatigue] = $this->projection($sejour, $now);
        $pct = HeroMedicalEvaluationService::santePourcentage($sante, $max);
        $retour = $this->retour($sejour, $now);

        return [
            'id' => $sejour->getId(),
            'heroId' => $hero->getId(),
            'nom' => $hero->getNom(),
            'initiales' => $hero->getInitiales(),
            'rang' => $hero->getRang(),
            'statut' => $sejour->getStatut()->value,
            'statutLabel' => $sejour->getStatut()->label(),
            'tone' => $sejour->getStatut()->tone(),
            'motif' => $sejour->getMotif()->value,
            'motifLabel' => $sejour->getMotif()->label(),
            'typeAdmission' => $sejour->getTypeAdmission()->label(),
            'pv' => (int) floor($sante + 1e-6),
            'pvMax' => $max,
            'sante' => (int) floor($pct + 1e-6),
            'fatigue' => (int) ceil($fatigue - 1e-6),
            'santeCritique' => $pct <= $this->config->santeCritique,
            'epuise' => $fatigue >= $this->config->fatigueCritique,
            'admisLe' => $sejour->getAdmisLe()->format(\DATE_ATOM),
            'sortiePrevue' => $sejour->getSortiePrevueLe()?->format(\DATE_ATOM),
            'secondesRestantes' => StatutSejour::RETOUR_QG === $sejour->getStatut()
                ? $retour['secondesRestantes'] ?? null
                : (StatutSejour::RETABLI === $sejour->getStatut() ? 0 : $this->recuperation->secondesRestantes($sejour, $now)),
            'tauxSanteHeure' => round($sejour->getTauxSante() * 60 * $this->config->facteurTemps, 1),
            'tauxFatigueHeure' => round($sejour->getTauxFatigue() * 60 * $this->config->facteurTemps, 1),
            'mission' => null === $sejour->getMission() ? null : [
                'id' => $sejour->getMission()->getId(),
                'nom' => $sejour->getMission()->getNom(),
                'url' => $this->urls->generate('app_mission_show', ['id' => $sejour->getMission()->getId()]),
            ],
            'retour' => $retour,
            'url' => $this->urls->generate('app_hero_show', ['id' => $hero->getId()]),
        ];
    }

    /**
     * Trajet de retour au format des déplacements de la carte (tracé jusqu'au QG), ou null.
     *
     * @return array<string, mixed>|null
     */
    public function retour(SejourInfirmerie $sejour, \DateTimeImmutable $now): ?array
    {
        $position = $this->recuperation->positionRetour($sejour, $now);
        $depart = $sejour->getDepartRetour();
        if (null === $position || null === $depart) {
            return null;
        }
        $fraction = $this->recuperation->progressionRetour($sejour, $now);
        $prevue = $sejour->getArriveeQgPrevueLe();

        return [
            'statut' => 'EN_DEPLACEMENT',
            'statutLabel' => $fraction >= 1.0 ? 'Arrivée imminente au QG' : 'Retour vers le QG',
            'progression' => (int) floor($fraction * 100),
            'distanceTotaleKm' => round((float) $sejour->getDistanceRetourKm(), 1),
            'distanceRestanteKm' => round($this->distances->distanceKm($position, $this->qg->coordonnees()), 1),
            'secondesRestantes' => null === $prevue ? 0 : max(0, $prevue->getTimestamp() - $now->getTimestamp()),
            'arriveePrevue' => $prevue?->format(\DATE_ATOM),
            'arriveeReelle' => null,
            'vitesse' => $sejour->getVitesseRetour(),
            'depart' => $depart->toArray(),
            'arrivee' => $this->qg->coordonnees()->toArray(),
            'position' => $position->toArray(),
            'destination' => $this->qg->nom,
        ];
    }

    /** @return array{0: float, 1: float} santé et fatigue projetées à $now (sans écriture) */
    public function projection(SejourInfirmerie $sejour, \DateTimeImmutable $now): array
    {
        $max = max(0, $sejour->getHero()->getPointsDeVieMaximum());
        $sante = HeroMedicalEvaluationService::bornerSante($sejour->getSanteActuelle(), $max);
        $fatigue = HeroMedicalEvaluationService::bornerFatigue($sejour->getFatigueActuelle());
        $depuis = $sejour->getDernierCalculLe();
        if (StatutSejour::EN_TRAITEMENT !== $sejour->getStatut() || null === $depuis) {
            return [$sante, $fatigue];
        }
        $minutes = max(0.0, ($now->getTimestamp() - $depuis->getTimestamp()) * $this->config->facteurTemps / 60);

        // Jamais au-delà des cibles : le rétablissement est acté par le worker, pas par la lecture.
        return [
            min(max($sante, (float) min($sejour->getSanteCible(), $max)), $sante + $sejour->getTauxSante() * $minutes),
            max(min($fatigue, (float) $sejour->getFatigueCible()), $fatigue - $sejour->getTauxFatigue() * $minutes),
        ];
    }
}
