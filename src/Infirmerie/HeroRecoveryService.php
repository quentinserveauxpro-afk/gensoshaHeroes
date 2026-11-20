<?php

namespace App\Infirmerie;

use App\Entity\GeoPosition;
use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Enum\HeroStatut;
use App\Enum\MotifInfirmerie;
use App\Enum\NiveauAlerte;
use App\Enum\SourceGeolocalisation;
use App\Enum\StatutSejour;
use App\Geo\Coordonnees;
use App\Geo\DistanceCalculator;
use App\Geo\EstimatedArrivalCalculator;
use App\Service\Mission\EffectiveStatsResolver;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Retour au QG et récupération des héros, calculés côté serveur à partir du temps écoulé.
 *
 * Retour : la position est une fonction pure du temps (comme les déplacements vers les
 * missions) ; aucune récupération pendant le trajet. L'arrivée est actée à l'heure prévue,
 * même si le passage du moteur est en retard.
 *
 * Récupération (par minute simulée, facteur de temps INFIRMERIE_FACTEUR_TEMPS) :
 *   santé   = SANTE_POINTS / PERIODE × vigueur × niveau × gravité × soutien × capacité
 *   fatigue = FATIGUE_POINTS / PERIODE × vigueur × soutien × capacité
 *     vigueur  = 0,75 + 0,5 × vigueur effective / 100 (bonus actifs inclus)
 *     niveau   = 1 + min(niveau, 100) / 200
 *     gravité  = 0,6 si admis à 0 PV ; 0,8 si santé critique ou blessure ; 1 sinon
 *     soutien  = 1 + BONUS_SOUTIEN si un autre héros disposant de l'aptitude de soutien est
 *                disponible au QG ; 1 + BONUS_SOUTIEN / 2 si seul le patient la possède
 *     capacité = min(1, max(0,5, lits / occupation))
 *
 * Seul l'intervalle [dernierCalculLe, maintenant] est calculé puis enregistré : relancer
 * immédiatement ne change rien, et un arrêt du serveur est rattrapé au passage suivant.
 * L'instant de rétablissement est calculé exactement (pas d'arrondi à la période du worker).
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
        private readonly EffectiveStatsResolver $stats,
        private readonly InfirmerieAlerteService $alertes,
    ) {
    }

    public function taux(SejourInfirmerie $sejour, ContexteRecuperation $contexte, \DateTimeImmutable $now): TauxRecuperation
    {
        $hero = $sejour->getHero();
        $vigueur = max(0, min(100, $this->stats->statsEffectives($hero, $now)['VIGUEUR'] ?? 0));
        $fVigueur = 0.75 + 0.5 * $vigueur / 100;
        $fNiveau = 1 + min(100, max(0, (int) $hero->getNiveau())) / 200;
        $pctAdmission = HeroMedicalEvaluationService::santePourcentage($sejour->getSanteAdmission(), $hero->getPointsDeVieMaximum());
        $fGravite = match (true) {
            $sejour->getSanteAdmission() <= 0 => 0.6,
            $pctAdmission <= $this->config->santeCritique, MotifInfirmerie::BLESSURE === $sejour->getMotif() => 0.8,
            default => 1.0,
        };
        $fSoutien = match (true) {
            $contexte->soutienPour((int) $hero->getId()) => 1 + $this->config->bonusSoutien,
            $this->possedeSoutien($hero) => 1 + $this->config->bonusSoutien / 2,
            default => 1.0,
        };
        $fCapacite = min(1.0, max(0.5, $this->config->capacite / max(1, $contexte->occupation)));

        return new TauxRecuperation(
            $this->config->tauxSanteBase() * $fVigueur * $fNiveau * $fGravite * $fSoutien * $fCapacite,
            $this->config->tauxFatigueBase() * $fVigueur * $fSoutien * $fCapacite,
            ['vigueur' => $fVigueur, 'niveau' => $fNiveau, 'gravite' => $fGravite, 'soutien' => $fSoutien, 'capacite' => $fCapacite],
        );
    }

    public function possedeSoutien(Hero $hero): bool
    {
        foreach ($hero->getAptitudes() as $aptitude) {
            if ($aptitude->getCode() === $this->config->aptitudeSoutien) {
                return true;
            }
        }

        return false;
    }

    /** Position du héros pendant son retour (calcul pur), ou null s'il n'est pas en route. */
    public function positionRetour(SejourInfirmerie $sejour, \DateTimeImmutable $now): ?Coordonnees
    {
        $depart = $sejour->getDepartRetour();
        if (StatutSejour::RETOUR_QG !== $sejour->getStatut() || null === $depart || null === $sejour->getArriveeQgPrevueLe()) {
            return null;
        }
        $fraction = $this->eta->fraction($sejour->getDepartRetourLe() ?? $now, $sejour->getArriveeQgPrevueLe(), $now);

        return $this->distances->pointIntermediaire($depart, $this->qg->coordonnees(), $fraction);
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
        // Seconde entière : les colonnes DATETIME tronquent les microsecondes, et un intervalle
        // ne doit jamais être compté deux fois d'un passage à l'autre.
        $now = self::secondeEntiere($now);
        $evenements = [];
        if (StatutSejour::RETOUR_EN_ATTENTE === $sejour->getStatut()) {
            $this->arriver($sejour, $now);
            $evenements[] = 'arrivee';
        }
        if (StatutSejour::RETOUR_QG === $sejour->getStatut()) {
            $prevue = $sejour->getArriveeQgPrevueLe();
            if (null !== $prevue && $prevue > $now) {
                return $evenements;
            }
            $this->arriver($sejour, null === $prevue ? $now : min($now, $prevue));
            $evenements[] = 'arrivee';
        }
        if (StatutSejour::EN_TRAITEMENT === $sejour->getStatut()) {
            if ($this->recuperer($sejour, $now, $contexte)) {
                $evenements[] = 'retabli';
            }
        }
        if (StatutSejour::RETABLI === $sejour->getStatut() && $this->config->sortieAutomatique) {
            $this->sortir($sejour, $sejour->getRetabliLe() ?? $now);
            $evenements[] = 'sorti';
        }

        return $evenements;
    }

    /** Arrivée au QG : début du traitement et repositionnement du héros sur le QG. */
    public function arriver(SejourInfirmerie $sejour, \DateTimeImmutable $date): void
    {
        $sejour->arriverAuQg($date);
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

    /** @return bool vrai si le héros vient d'être déclaré rétabli */
    private function recuperer(SejourInfirmerie $sejour, \DateTimeImmutable $now, ContexteRecuperation $contexte): bool
    {
        $hero = $sejour->getHero();
        $depuis = $sejour->getDernierCalculLe() ?? $sejour->getDebutTraitementLe() ?? $now;
        $taux = $this->taux($sejour, $contexte, $now);
        $sejour->setTaux($taux->santeParMinute, $taux->fatigueParMinute);

        $max = max(0, $hero->getPointsDeVieMaximum());
        $sante = HeroMedicalEvaluationService::bornerSante($sejour->getSanteActuelle(), $max);
        $fatigue = HeroMedicalEvaluationService::bornerFatigue($sejour->getFatigueActuelle());
        $cibleSante = min($sejour->getSanteCible(), $max);
        $cibleFatigue = $sejour->getFatigueCible();

        // Minutes simulées nécessaires pour atteindre chaque cible depuis l'état enregistré.
        $besoin = max(
            $this->minutesPour($cibleSante - $sante, $taux->santeParMinute),
            $this->minutesPour($fatigue - $cibleFatigue, $taux->fatigueParMinute),
        );
        $ecoulees = max(0.0, ($now->getTimestamp() - $depuis->getTimestamp()) * $this->config->facteurTemps / 60);

        if (null !== $besoin && $besoin <= $ecoulees) {
            $retabliLe = min($now, $this->ajouterMinutes($depuis, $besoin));
            $sejour->enregistrerRecuperation(max($sante, (float) $cibleSante), min($fatigue, (float) $cibleFatigue), $retabliLe, $retabliLe);
            $sejour->marquerRetabli($retabliLe);
            $this->synchroniserHero($sejour);
            $this->alertes->pourSejour($sejour, InfirmerieAlerteService::RETABLI, 'Récupération terminée',
                sprintf('%s est rétabli (%d %% de santé, fatigue %d %%)%s.', $hero->getNom(), (int) round($this->medical->pourcentage($hero)), $hero->getFatigue(),
                    $this->config->sortieAutomatique ? ' et quitte l\'infirmerie' : ' : sortie à valider par un opérateur héros'), NiveauAlerte::INFO, $retabliLe);

            return true;
        }

        $sante = min((float) $max, $sante + $taux->santeParMinute * $ecoulees);
        $fatigue = max(0.0, $fatigue - $taux->fatigueParMinute * $ecoulees);
        $restant = null === $besoin ? null : $besoin - $ecoulees;
        $sejour->enregistrerRecuperation($sante, $fatigue, $now, null === $restant ? null : $this->ajouterMinutes($now, $restant));
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

    /** Estimation (secondes réelles) du temps restant avant rétablissement, null si inconnu. */
    public function secondesRestantes(SejourInfirmerie $sejour, \DateTimeImmutable $now): ?int
    {
        $prevue = $sejour->getSortiePrevueLe();

        return null === $prevue ? null : max(0, $prevue->getTimestamp() - $now->getTimestamp());
    }

    /** Les valeurs entières du héros dérivent des valeurs décimales du séjour (bornées). */
    private function synchroniserHero(SejourInfirmerie $sejour): void
    {
        $hero = $sejour->getHero();
        $max = max(0, $hero->getPointsDeVieMaximum());
        $hero->setPointsDeVie((int) floor(HeroMedicalEvaluationService::bornerSante($sejour->getSanteActuelle(), $max) + 1e-6));
        $hero->setFatigue((int) ceil(HeroMedicalEvaluationService::bornerFatigue($sejour->getFatigueActuelle()) - 1e-6));
    }

    public static function secondeEntiere(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimestamp($date->getTimestamp());
    }

    private function minutesPour(float $ecart, float $taux): ?float
    {
        if ($ecart <= 0) {
            return 0.0;
        }

        return $taux > 0 ? $ecart / $taux : null;
    }

    /** Ajoute des minutes simulées, converties en temps réel par le facteur de temps. */
    private function ajouterMinutes(\DateTimeImmutable $date, float $minutesSimulees): \DateTimeImmutable
    {
        $secondes = (int) ceil($minutesSimulees * 60 / max(0.001, $this->config->facteurTemps));

        return $date->modify(sprintf('+%d seconds', max(0, $secondes)));
    }
}
