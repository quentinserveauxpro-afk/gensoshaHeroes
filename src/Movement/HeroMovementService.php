<?php

namespace App\Movement;

use App\Entity\AffectationMission;
use App\Entity\GeoPosition;
use App\Entity\HeroMovement;
use App\Entity\Mission;
use App\Enum\AffectationStatut;
use App\Enum\SourceGeolocalisation;
use App\Geo\CarteConfiguration;
use App\Geo\Coordonnees;
use App\Geo\DistanceCalculator;
use App\Geo\EstimatedArrivalCalculator;
use App\Repository\HeroMovementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Déplacements des héros vers les missions (serveur = source de vérité).
 *
 * - Trajet simulé en ligne droite sur le grand cercle (pas d'itinéraire routier réel).
 * - La position courante est une fonction pure du temps : position(t) = interpolation(départ,
 *   arrivée, (t − départ) / (arrivée prévue − départ)). Un rechargement de page ou un redémarrage
 *   du worker reprend donc exactement au bon endroit.
 * - Seul actualiser() (appelé par app:missions:update) persiste l'arrivée : le navigateur ne
 *   peut jamais décider qu'un héros est arrivé.
 */
final class HeroMovementService
{
    /** En dessous de cette distance (km), le héros est considéré déjà sur place. */
    public const DISTANCE_SUR_PLACE_KM = 0.05;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HeroMovementRepository $mouvements,
        private readonly DistanceCalculator $distances,
        private readonly EstimatedArrivalCalculator $eta,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Crée les déplacements manquants des héros « en route » d'une mission (idempotent).
     *
     * @return list<HeroMovement> déplacements créés
     */
    public function demarrerPourMission(Mission $mission): array
    {
        $crees = [];
        foreach ($mission->getAffectationsActives() as $affectation) {
            if (AffectationStatut::AFFECTE !== $affectation->getStatut()) {
                continue;
            }
            $existant = $this->mouvements->findDernierPourHeroEtMission($affectation->getHero(), $mission);
            if (null !== $existant && $existant->getDateDepart() >= $affectation->getDateAffectation()) {
                continue;
            }
            $mouvement = $this->demarrer($affectation);
            if (null !== $mouvement) {
                $crees[] = $mouvement;
            }
        }

        return $crees;
    }

    /**
     * Crée le déplacement d'un héros affecté. Retourne null si la mission n'est pas géolocalisée
     * (le héros reste « en route » sans trajet calculable : aucune position n'est inventée).
     */
    public function demarrer(AffectationMission $affectation): ?HeroMovement
    {
        $hero = $affectation->getHero();
        $mission = $affectation->getMission();
        $arrivee = $mission?->getCoordonnees();
        if (null === $hero || null === $mission || null === $arrivee) {
            return null;
        }

        $now = $this->clock->now();
        $depart = $hero->getCoordonnees() ?? CarteConfiguration::qg();
        $distance = $this->distances->distanceKm($depart, $arrivee);
        $vitesse = $this->eta->vitessePourHero($hero);

        $mouvement = new HeroMovement(
            $hero,
            $mission,
            $depart,
            $arrivee,
            round($distance, 3),
            $vitesse,
            $now,
            $this->eta->dateArrivee($now, $distance, $vitesse),
            $affectation,
        );
        $this->em->persist($mouvement);

        if ($distance <= self::DISTANCE_SUR_PLACE_KM) {
            $this->marquerArrivee($mouvement, $now);
        } else {
            // Départ immédiat : le héros est en route dès le déploiement.
            $mouvement->avancer($depart, 0.0, $now);
        }

        return $mouvement;
    }

    /** Calcul pur (sans écriture) de l'état d'un déplacement à l'instant $now. */
    public function etatA(HeroMovement $mouvement, \DateTimeImmutable $now): EtatDeplacement
    {
        if (!$mouvement->isEnCours()) {
            $position = $mouvement->getPositionActuelle();

            return new EtatDeplacement(
                $position,
                $mouvement->getProgression() / 100,
                $this->distances->distanceKm($position, $mouvement->getArrivee()),
                0,
                \App\Enum\MouvementStatut::ARRIVE === $mouvement->getStatut(),
            );
        }

        $fraction = $this->eta->fraction($mouvement->getDateDepart(), $mouvement->getDateArriveePrevue(), $now);
        $position = $this->distances->pointIntermediaire($mouvement->getDepart(), $mouvement->getArrivee(), $fraction);

        return new EtatDeplacement(
            $position,
            $fraction,
            max(0.0, $mouvement->getDistanceKm() * (1 - $fraction)),
            $this->eta->secondesRestantes($mouvement->getDateArriveePrevue(), $now),
            $fraction >= 1.0,
        );
    }

    /**
     * Fait avancer un déplacement en cours et persiste la nouvelle position.
     *
     * @return bool vrai si le héros vient d'arriver
     */
    public function actualiser(HeroMovement $mouvement, ?\DateTimeImmutable $now = null): bool
    {
        if (!$mouvement->isEnCours()) {
            return false;
        }
        $now ??= $this->clock->now();
        $etat = $this->etatA($mouvement, $now);
        if ($etat->arrive) {
            // Heure réelle d'arrivée = heure prévue si le worker passe en retard (jamais dans le futur).
            $this->marquerArrivee($mouvement, min($now, $mouvement->getDateArriveePrevue()));

            return true;
        }
        $mouvement->avancer($etat->position, $etat->progression(), $now);

        return false;
    }

    /** Interrompt les déplacements encore en cours d'une mission (clôture, annulation). */
    public function interrompreMission(Mission $mission, ?\DateTimeImmutable $now = null): int
    {
        $now ??= $this->clock->now();
        $n = 0;
        foreach ($this->mouvements->findPourMission($mission) as $mouvement) {
            if ($mouvement->isEnCours()) {
                $mouvement->interrompre($now);
                ++$n;
            }
        }

        return $n;
    }

    private function marquerArrivee(HeroMovement $mouvement, \DateTimeImmutable $date): void
    {
        $mouvement->arriver($date);

        $affectation = $mouvement->getAffectation();
        if (null !== $affectation && AffectationStatut::AFFECTE === $affectation->getStatut()) {
            $affectation->setStatut(AffectationStatut::SUR_PLACE);
            $affectation->setDateArrivee($date);
        }

        $hero = $mouvement->getHero();
        $destination = $mouvement->getArrivee();
        $position = $hero->getGeoPosition();
        if (null === $position) {
            $position = new GeoPosition($destination, SourceGeolocalisation::DEPLACEMENT);
            $hero->setGeoPosition($position);
            $this->em->persist($position);
        } else {
            $position->setCoordonnees($destination, SourceGeolocalisation::DEPLACEMENT);
        }
        $position->setLibelle($mouvement->getMission()->getLocalisation() ?? $mouvement->getMission()->getNom());
    }

    public function distanceVersMission(\App\Entity\Hero $hero, Mission $mission): ?float
    {
        $a = $hero->getCoordonnees();
        $b = $mission->getCoordonnees();

        return null === $a || null === $b ? null : $this->distances->distanceArrondie($a, $b);
    }

    public function coordonneesDepart(\App\Entity\Hero $hero): Coordonnees
    {
        return $hero->getCoordonnees() ?? CarteConfiguration::qg();
    }
}
