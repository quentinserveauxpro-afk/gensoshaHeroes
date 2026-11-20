<?php

namespace App\Infirmerie;

use App\Entity\GeoPosition;
use App\Entity\SejourInfirmerie;
use App\Enum\HeroStatut;
use App\Enum\NiveauAlerte;
use App\Enum\SourceGeolocalisation;
use App\Enum\StatutSejour;
use App\GameTime\InfirmaryRecoveryTimeCalculator;
use App\Geo\Coordonnees;
use App\Geo\DistanceCalculator;
use App\Geo\EstimatedArrivalCalculator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Retour au QG et récupération des héros, calculés côté serveur à partir des dates enregistrées.
 *
 * Retour : position = fonction pure du temps ; aucune récupération pendant le trajet ; l'arrivée
 * est actée à l'heure prévue, même si le worker passe en retard.
 *
 * Traitement : à l'arrivée, la durée totale est fixée par InfirmaryRecoveryTimeCalculator
 * (1 à 15 min) et enregistrée comme date de sortie prévue. Ensuite, à chaque passage :
 *
 *   p      = (maintenant − dernierCalcul) / (sortiePrevue − dernierCalcul), borné à [0, 1]
 *   santé  = santé enregistrée   + p × (cible − santé enregistrée)
 *   fatigue = fatigue enregistrée − p × (fatigue enregistrée − cible)
 *
 * Interpoler depuis le dernier point enregistré équivaut, pour un séjour neuf, à
 * santéAdmission + (temps écoulé / durée) × (cible − santéAdmission) ; c'est aussi correct
 * après une interruption ou une normalisation. La santé ne diminue jamais, la fatigue
 * n'augmente jamais ; à la date de sortie prévue, les cibles sont appliquées exactement.
 * Calculs à la seconde entière : relancer au même instant ne change rien.
 */
final class HeroRecoveryService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InfirmerieConfiguration $config,
        private readonly HeroMedicalEvaluationService $medical,
        private readonly QgConfiguration $qg,
        private readonly DistanceCalculator $distances,
        private readonly EstimatedArrivalCalculator $eta,
        private readonly InfirmaryRecoveryTimeCalculator $dureeSoin,
        private readonly InfirmerieAlerteService $alertes,
        private readonly ?InfirmerieContexteProvider $contextes = null,
    ) {
    }

    /** Position du héros pendant son retour (calcul pur), ou null s'il n'est pas en route. */
    public function positionRetour(SejourInfirmerie $sejour, \DateTimeImmutable $now): ?Coordonnees
    {
        $depart = $sejour->getDepartRetour();
        if (StatutSejour::RETOUR_QG !== $sejour->getStatut() || null === $depart || null === $sejour->getArriveeQgPrevueLe()) {
            return null;
        }

        return $this->distances->pointIntermediaire($depart, $this->qg->coordonnees(), $this->progressionRetour($sejour, $now));
    }

    public function progressionRetour(SejourInfirmerie $sejour, \DateTimeImmutable $now): float
    {
        if (null === $sejour->getArriveeQgPrevueLe()) {
            return StatutSejour::RETOUR_QG === $sejour->getStatut() ? 0.0 : 1.0;
        }

        return $this->eta->fraction($sejour->getDepartRetourLe() ?? $now, $sejour->getArriveeQgPrevueLe(), $now);
    }

    /**
     * Fait évoluer un séjour actif jusqu'à $now (idempotent).
     *
     * @return list<string> événements : arrivee, retabli, sorti
     */
    public function actualiser(SejourInfirmerie $sejour, \DateTimeImmutable $now, ContexteRecuperation $contexte): array
    {
        $now = self::secondeEntiere($now);
        $evenements = [];
        if (StatutSejour::RETOUR_EN_ATTENTE === $sejour->getStatut()) {
            $this->arriver($sejour, $now, $contexte);
            $evenements[] = 'arrivee';
        }
        if (StatutSejour::RETOUR_QG === $sejour->getStatut()) {
            $prevue = $sejour->getArriveeQgPrevueLe();
            if (null !== $prevue && $prevue > $now) {
                return $evenements;
            }
            $this->arriver($sejour, self::secondeEntiere(null === $prevue ? $now : min($now, $prevue)), $contexte);
            $evenements[] = 'arrivee';
        }
        if (StatutSejour::EN_TRAITEMENT === $sejour->getStatut() && $this->recuperer($sejour, $now, $contexte)) {
            $evenements[] = 'retabli';
        }
        if (StatutSejour::RETABLI === $sejour->getStatut() && $this->config->sortieAutomatique) {
            $this->sortir($sejour, $sejour->getRetabliLe() ?? $now);
            $evenements[] = 'sorti';
        }

        return $evenements;
    }

    /** Arrivée au QG : début du traitement, durée fixée, héros repositionné sur le QG. */
    public function arriver(SejourInfirmerie $sejour, \DateTimeImmutable $date, ?ContexteRecuperation $contexte = null): void
    {
        $sejour->arriverAuQg($date);
        $this->planifier($sejour, $date, $contexte ?? $this->contextes?->contexte(null, null === $sejour->getId() ? 1 : 0) ?? new ContexteRecuperation());

        $hero = $sejour->getHero();
        $position = $hero->getGeoPosition();
        if (null === $position) {
            $position = new GeoPosition($this->qg->coordonnees(), SourceGeolocalisation::DEPLACEMENT);
            $hero->setGeoPosition($position);
            $this->em->persist($position);
        } else {
            $position->setCoordonnees($this->qg->coordonnees(), SourceGeolocalisation::DEPLACEMENT);
        }
        $position->setLibelle($this->qg->nom);

        $this->alertes->pourSejour($sejour, InfirmerieAlerteService::ARRIVEE, 'Arrivée au QG',
            sprintf('%s est arrivé à l\'infirmerie (%s) : traitement en cours.', $hero->getNom(), $this->qg->nom), NiveauAlerte::INFO, $date);
    }

    /** Fixe la durée du traitement à partir de $debut (date de sortie prévue). */
    private function planifier(SejourInfirmerie $sejour, \DateTimeImmutable $debut, ContexteRecuperation $contexte): void
    {
        $duree = $this->dureeSoin->duree($sejour, $contexte, $debut);
        [$sante, $fatigue] = $this->depart($sejour);
        [$cibleSante, $cibleFatigue] = $this->cibles($sejour, $sante, $fatigue);
        $minutes = max(1 / 60, $duree / 60);
        // Taux informatifs (par minute réelle) : la progression officielle reste celle des dates.
        $sejour->setTaux(($cibleSante - $sante) / $minutes, ($fatigue - $cibleFatigue) / $minutes);
        $sejour->enregistrerRecuperation($sante, $fatigue, $debut, $debut->modify(sprintf('+%d seconds', $duree)));
    }

    /**
     * État projeté à $now sans écriture (santé, fatigue, progression du soin de 0 à 1).
     *
     * @return array{0: float, 1: float, 2: float}
     */
    public function etatA(SejourInfirmerie $sejour, \DateTimeImmutable $now): array
    {
        [$sante, $fatigue] = $this->depart($sejour);
        $now = self::secondeEntiere($now);
        $debut = $sejour->getDebutTraitementLe();
        $fin = $sejour->getSortiePrevueLe();
        if (StatutSejour::RETABLI === $sejour->getStatut() || (null !== $sejour->getRetabliLe())) {
            return [$sante, $fatigue, 1.0];
        }
        if (StatutSejour::EN_TRAITEMENT !== $sejour->getStatut() || null === $fin) {
            return [$sante, $fatigue, 0.0];
        }
        [$cibleSante, $cibleFatigue] = $this->cibles($sejour, $sante, $fatigue);
        $depuis = $sejour->getDernierCalculLe() ?? $debut ?? $now;
        $p = self::fraction($depuis, $fin, $now);
        $global = null === $debut ? $p : self::fraction($debut, $fin, $now);

        return [$sante + $p * ($cibleSante - $sante), $fatigue - $p * ($fatigue - $cibleFatigue), $global];
    }

    /** @return bool vrai si le héros vient d'être déclaré rétabli */
    private function recuperer(SejourInfirmerie $sejour, \DateTimeImmutable $now, ContexteRecuperation $contexte): bool
    {
        if (null === $sejour->getSortiePrevueLe()) {
            // Séjour antérieur à l'échelle accélérée sans date de fin : planifié depuis maintenant.
            $this->planifier($sejour, $now, $contexte);
        }
        $fin = $sejour->getSortiePrevueLe();
        [$sante, $fatigue] = $this->etatA($sejour, $now);

        if ($now >= $fin) {
            [$cibleSante, $cibleFatigue] = $this->cibles($sejour, ...$this->depart($sejour));
            $sejour->enregistrerRecuperation($cibleSante, $cibleFatigue, $fin, $fin);
            $sejour->marquerRetabli($fin);
            $this->synchroniserHero($sejour);
            $hero = $sejour->getHero();
            $this->alertes->pourSejour($sejour, InfirmerieAlerteService::RETABLI, 'Récupération terminée',
                sprintf('%s est rétabli (%d %% de santé, fatigue %d %%)%s.', $hero->getNom(), (int) round($this->medical->pourcentage($hero)), $hero->getFatigue(),
                    $this->config->sortieAutomatique ? ' et quitte l\'infirmerie' : ' : sortie à valider par un opérateur héros'), NiveauAlerte::INFO, $fin);

            return true;
        }

        $sejour->enregistrerRecuperation($sante, $fatigue, $now, $fin);
        $this->synchroniserHero($sejour);

        return false;
    }

    /** Sortie de l'infirmerie : le héros redevient sélectionnable (sans réaffectation automatique). */
    public function sortir(SejourInfirmerie $sejour, \DateTimeImmutable $date): void
    {
        if (StatutSejour::RETABLI !== $sejour->getStatut()) {
            throw new InfirmerieException(sprintf('%s n\'est pas encore rétabli : sortie impossible.', $sejour->getHero()->getNom()));
        }
        $sejour->sortir($date);
        $hero = $sejour->getHero();
        if (\in_array($hero->getStatut(), [HeroStatut::BLESSE, HeroStatut::REPOS], true)) {
            $hero->setStatut(HeroStatut::DISPONIBLE);
        }
    }

    /** Secondes réelles restantes avant rétablissement (jamais négatif), null si non planifié. */
    public function secondesRestantes(SejourInfirmerie $sejour, \DateTimeImmutable $now): ?int
    {
        $prevue = $sejour->getSortiePrevueLe();

        return null === $prevue ? null : max(0, $prevue->getTimestamp() - $now->getTimestamp());
    }

    /** Durée totale prévue du traitement (secondes), null si non planifié. */
    public function dureeTraitement(SejourInfirmerie $sejour): ?int
    {
        $debut = $sejour->getDebutTraitementLe();
        $fin = $sejour->getSortiePrevueLe();

        return null === $debut || null === $fin ? null : max(0, $fin->getTimestamp() - $debut->getTimestamp());
    }

    public static function secondeEntiere(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimestamp($date->getTimestamp());
    }

    public static function fraction(\DateTimeImmutable $debut, \DateTimeImmutable $fin, \DateTimeImmutable $now): float
    {
        $total = $fin->getTimestamp() - $debut->getTimestamp();
        if ($total <= 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, ($now->getTimestamp() - $debut->getTimestamp()) / $total));
    }

    /** @return array{0: float, 1: float} valeurs enregistrées, bornées */
    private function depart(SejourInfirmerie $sejour): array
    {
        return [
            HeroMedicalEvaluationService::bornerSante($sejour->getSanteActuelle(), $sejour->getHero()->getPointsDeVieMaximum()),
            HeroMedicalEvaluationService::bornerFatigue($sejour->getFatigueActuelle()),
        ];
    }

    /**
     * Cibles effectives : la santé ne baisse jamais, la fatigue n'augmente jamais.
     *
     * @return array{0: float, 1: float}
     */
    private function cibles(SejourInfirmerie $sejour, float $sante, float $fatigue): array
    {
        $max = max(0, $sejour->getHero()->getPointsDeVieMaximum());

        return [
            max($sante, (float) min($sejour->getSanteCible(), $max)),
            min($fatigue, (float) $sejour->getFatigueCible()),
        ];
    }

    /** Les valeurs entières du héros dérivent des valeurs décimales du séjour (bornées). */
    private function synchroniserHero(SejourInfirmerie $sejour): void
    {
        $hero = $sejour->getHero();
        $max = max(0, $hero->getPointsDeVieMaximum());
        $hero->setPointsDeVie((int) floor(HeroMedicalEvaluationService::bornerSante($sejour->getSanteActuelle(), $max) + 1e-6));
        $hero->setFatigue((int) ceil(HeroMedicalEvaluationService::bornerFatigue($sejour->getFatigueActuelle()) - 1e-6));
    }
}
