<?php

namespace App\Simulation;

use App\Entity\AffectationMission;
use App\Entity\Alerte;
use App\Entity\Hero;
use App\Entity\Mission;
use App\Entity\MissionSimulationState;
use App\Entity\ObjectifMission;
use App\Enum\AffectationStatut;
use App\Enum\MissionStatut;
use App\Enum\NiveauAlerte;
use App\Enum\TypeAdmission;
use App\Geo\EstimatedArrivalCalculator;
use App\Infirmerie\HeroRepatriationService;
use App\Infirmerie\InfirmerieConfiguration;
use App\Movement\HeroMovementService;
use App\Repository\MissionSimulationStateRepository;
use App\Service\Mission\EffectiveStatsResolver;
use App\Service\Mission\MissionLifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Moteur serveur d'évolution des missions en cours (aucune logique métier côté navigateur).
 *
 * Phases : APPROCHE (aucun héros sur place) → ENGAGEMENT (dès le premier héros arrivé)
 * → TERMINEE / ECHOUEE.
 *
 * Pendant l'engagement, le temps est découpé en étapes de PAS_SECONDES secondes simulées
 * (facteur d'accélération SIMULATION_FACTEUR_TEMPS). À chaque étape, pour les seuls héros
 * réellement arrivés et encore debout :
 *   1. puissance de l'équipe (stats, niveau, bonus actifs, synergies)   — TeamPowerCalculator
 *   2. menace courante (vilains, urgence, difficulté, progression)        — VillainThreatCalculator
 *   3. progression de chaque objectif (prérequis, compétences, menace)   — ObjectiveProgressCalculator
 *   4. dommages de chaque héros (vigueur, combat, menace, aléa injecté)   — HeroHealthCalculator
 * puis le taux de réussite en direct est recalculé (LiveSuccessRateCalculator).
 *
 * Fin de mission : succès quand tous les objectifs obligatoires sont atteints ; échec si
 * l'échéance (timer) est dépassée ou si plus aucun héros ne peut agir ni n'est en route.
 *
 * Idempotence : seules les étapes entières écoulées depuis MissionSimulationState::dernierTick
 * sont calculées ; un second passage immédiat ne fait rien. L'aléa est dérivé d'une graine
 * (mission, étape, héros) : une étape recalculée donne toujours le même résultat.
 */
final class MissionSimulationService
{
    public const PAS_SECONDES = 5;
    public const ETAPES_MAX_PAR_PASSAGE = 720;
    public const OBJECTIF_PAR_DEFAUT = 'Neutraliser la menace et sécuriser la zone';

    public const PHASE_PLANIFIEE = 'PLANIFIEE';
    public const PHASE_APPROCHE = 'APPROCHE';
    public const PHASE_ENGAGEMENT = 'ENGAGEMENT';
    public const PHASE_TERMINEE = 'TERMINEE';
    public const PHASE_ECHOUEE = 'ECHOUEE';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MissionSimulationStateRepository $etats,
        private readonly TeamPowerCalculator $puissance,
        private readonly VillainThreatCalculator $menaces,
        private readonly ObjectiveProgressCalculator $objectifs,
        private readonly HeroHealthCalculator $sante,
        private readonly LiveSuccessRateCalculator $tauxDirect,
        private readonly EffectiveStatsResolver $statsResolver,
        private readonly RandomGeneratorInterface $alea,
        private readonly HeroMovementService $mouvements,
        private readonly MissionLifecycleService $cycle,
        private readonly EstimatedArrivalCalculator $temps,
        private readonly ClockInterface $clock,
        private readonly ?HeroRepatriationService $rapatriement = null,
        private readonly ?InfirmerieConfiguration $infirmerie = null,
    ) {
    }

    public function simuler(Mission $mission, ?\DateTimeImmutable $now = null): RapportSimulation
    {
        $now ??= $this->clock->now();
        if (MissionStatut::EN_COURS !== $mission->getStatut()) {
            return new RapportSimulation((int) $mission->getId(), $this->phase($mission, null), 0);
        }

        $etat = $this->etat($mission, $now);
        $evenements = [];

        if (null === $etat->getDateEngagement()) {
            $vivants = $this->herosOperationnels($mission);
            if ([] === $vivants) {
                $etat->avancer(0, $now);
                if ($this->echeanceDepassee($mission, $now)) {
                    return $this->terminer($mission, $etat, false, 'Échéance dépassée avant l\'arrivée de l\'équipe', $now);
                }
                if (0 === $this->nombreEnRoute($mission) && [] !== $mission->getAffectationsActives()) {
                    return $this->terminer($mission, $etat, false, 'Aucun héros en état d\'intervenir', $now);
                }

                return new RapportSimulation((int) $mission->getId(), self::PHASE_APPROCHE, 0);
            }
            $engagement = $this->dateEngagement($mission, $now);
            $etat->engager($engagement, $this->menaces->menaceInitiale($mission, $this->puissance->puissance($vivants, $mission, $engagement)));
            $etat->avancer(0, $engagement);
            $this->garantirObjectif($mission);
            $etat->journaliser($engagement, 'info', 'Engagement : premier héros sur place.');
            $evenements[] = 'engagement';
        }

        // L'instant de l'étape n est toujours engagement + n × pas : aucune dérive due à la
        // troncature des DATETIME, et un passage répété ne recalcule jamais une étape faite.
        $pasReel = self::PAS_SECONDES / $this->temps->getFacteurTemps();
        $base = $etat->getDateEngagement();
        $dues = (int) floor($this->secondes($base, $now) / $pasReel + 1e-9);
        $aFaire = max(0, min(self::ETAPES_MAX_PAR_PASSAGE, $dues - $etat->getNombreEtapes()));

        $faites = 0;
        $gainMoyen = 0.0;
        for ($i = 1; $i <= $aFaire; ++$i) {
            $t = $this->ajouter($base, ($etat->getNombreEtapes() + $i) * $pasReel);
            $vivants = $this->herosOperationnels($mission, $t);
            if ([] === $vivants) {
                // Plus personne en état d'agir : ces étapes sont consommées sans effet.
                $faites = $aFaire;
                break;
            }
            $gainMoyen = $this->etape($mission, $etat, $vivants, $etat->getNombreEtapes() + $i, $t, $evenements);
            $faites = $i;
            if ($mission->isObjectifsObligatoiresAtteints()) {
                break;
            }
        }
        $etat->avancer($faites, $faites > 0 ? $this->ajouter($base, ($etat->getNombreEtapes() + $faites) * $pasReel) : $etat->getDernierTick());

        $progression = $this->progressionObligatoire($mission, $etat);
        $etat->setMenaceActuelle($this->menaces->menaceCourante((float) $etat->getMenaceInitiale(), $progression));

        $vivants = $this->herosOperationnels($mission);
        $santeMoyenne = [] === $vivants ? 0.0 : array_sum(array_map(static fn (Hero $h) => $h->getSantePourcentage(), $vivants)) / \count($vivants) / 100;
        $etapesRestantes = (int) floor(max(0, ($mission->getDateFinPrevue()?->getTimestamp() ?? $now->getTimestamp()) - $now->getTimestamp()) / $pasReel);
        $taux = $this->tauxDirect->calculer(
            $progression,
            $gainMoyen > 0 ? $gainMoyen : $this->estimerGain($mission, $etat, $vivants, $now),
            $etapesRestantes,
            $santeMoyenne,
            $mission->isObjectifsObligatoiresAtteints(),
            [] !== $vivants || $this->nombreEnRoute($mission) > 0,
        );
        $etat->setTauxReussiteDirect($taux);
        $mission->setTauxDeReussite($taux);

        if ($mission->isObjectifsObligatoiresAtteints()) {
            return $this->terminer($mission, $etat, true, 'Tous les objectifs obligatoires sont atteints', $now, $faites);
        }
        if ([] === $vivants && 0 === $this->nombreEnRoute($mission)) {
            return $this->terminer($mission, $etat, false, 'Équipe hors de combat', $now, $faites);
        }
        if ($this->echeanceDepassee($mission, $now)) {
            return $this->terminer($mission, $etat, false, 'Échéance dépassée', $now, $faites);
        }

        return new RapportSimulation((int) $mission->getId(), self::PHASE_ENGAGEMENT, $faites, false, null, $evenements);
    }

    /**
     * Une étape de simulation. Retourne le gain moyen des objectifs obligatoires non terminés.
     *
     * @param list<Hero>   $vivants
     * @param list<string> $evenements
     */
    private function etape(Mission $mission, MissionSimulationState $etat, array $vivants, int $numero, \DateTimeImmutable $t, array &$evenements): float
    {
        $puissance = $this->puissance->puissance($vivants, $mission, $t);
        $menace = $this->menaces->menaceCourante((float) $etat->getMenaceInitiale(), $this->progressionObligatoire($mission, $etat));

        $gains = [];
        foreach ($mission->getObjectifs() as $objectif) {
            if ($objectif->isTermine() || null === $objectif->getId()) {
                continue;
            }
            $resultat = $this->objectifs->calculer($objectif, $mission, $vivants, $puissance, $menace);
            if ($objectif->isObligatoire()) {
                $gains[] = $resultat->gain;
            }
            if ($resultat->gain <= 0) {
                continue;
            }
            $valeur = $etat->getProgressionPrecise($objectif->getId(), $objectif->getProgression()) + $resultat->gain;
            $etat->setProgressionPrecise($objectif->getId(), $valeur);
            $this->repartirContribution($etat, $vivants, $objectif, $resultat->gain, $t);
            if ($valeur >= 100.0) {
                $objectif->setTermine(true);
                $objectif->setDateAccomplissement($t);
                $etat->journaliser($t, 'ok', sprintf('Objectif atteint : %s', $objectif->getTitre()));
                $evenements[] = 'objectif:'.$objectif->getId();
            } else {
                $objectif->setProgression((int) floor($valeur));
            }
        }

        foreach ($vivants as $hero) {
            $stats = $this->statsResolver->statsEffectives($hero, $t);
            $graine = sprintf('mission:%d:etape:%d:hero:%d', $mission->getId(), $numero, $hero->getId());
            $degats = $this->sante->degats($stats, $menace, $mission->getDifficulte(), $this->alea->valeur($graine));
            $this->fatiguer($etat, $hero);
            if (0 === $degats) {
                $this->evaluerMedicalement($mission, $etat, $hero, $t, $evenements);
                continue;
            }
            $pv = $this->sante->appliquer($hero, $degats);
            if ($this->sante->estCritique($hero) && !$etat->aEmisAlerteSante((int) $hero->getId())) {
                $etat->marquerAlerteSante((int) $hero->getId());
                $this->alerter($mission, $t, 'Santé critique', sprintf('%s : santé %d %% sur « %s »', $hero->getNom(), $hero->getSantePourcentage(), $mission->getNom()), NiveauAlerte::CRITIQUE, 'SAN', (int) $hero->getId());
                $etat->journaliser($t, 'danger', sprintf('%s en état critique (%d %%).', $hero->getNom(), $hero->getSantePourcentage()));
                $evenements[] = 'critique:'.$hero->getId();
            }
            if (0 === $pv) {
                $etat->journaliser($t, 'danger', sprintf('%s est hors de combat.', $hero->getNom()));
                $evenements[] = 'ko:'.$hero->getId();
            }
            $this->evaluerMedicalement($mission, $etat, $hero, $t, $evenements);
        }

        return [] === $gains ? 0.0 : array_sum($gains) / \count($gains);
    }

    /** Fatigue gagnée pendant une étape d'engagement (valeur décimale conservée dans l'état). */
    private function fatiguer(MissionSimulationState $etat, Hero $hero): void
    {
        $gain = $this->infirmerie?->fatigueParEtape ?? 0.0;
        if ($gain <= 0) {
            return;
        }
        $valeur = $etat->getFatiguePrecise((int) $hero->getId(), $hero->getFatigue()) + $gain;
        $etat->setFatiguePrecise((int) $hero->getId(), $valeur);
        $hero->setFatigue((int) floor($valeur));
    }

    /**
     * Après dégâts et progression : rapatriement si la santé ou la fatigue atteint le seuil
     * critique (décision serveur). Le héros cesse aussitôt de contribuer aux objectifs.
     *
     * @param list<string> $evenements
     */
    private function evaluerMedicalement(Mission $mission, MissionSimulationState $etat, Hero $hero, \DateTimeImmutable $t, array &$evenements): void
    {
        $sejour = $this->rapatriement?->rapatrierSiNecessaire($hero, $t, TypeAdmission::AUTOMATIQUE);
        if (null !== $sejour) {
            $evenements[] = 'rapatrie:'.$hero->getId();
        }
    }

    /** @param list<Hero> $vivants */
    private function estimerGain(Mission $mission, MissionSimulationState $etat, array $vivants, \DateTimeImmutable $now): float
    {
        if ([] === $vivants) {
            return 0.0;
        }
        $puissance = $this->puissance->puissance($vivants, $mission, $now);
        $menace = $etat->getMenaceActuelle();
        $gains = [];
        foreach ($mission->getObjectifsObligatoires() as $objectif) {
            if (!$objectif->isTermine()) {
                $gains[] = $this->objectifs->calculer($objectif, $mission, $vivants, $puissance, $menace)->gain;
            }
        }

        return [] === $gains ? 0.0 : array_sum($gains) / \count($gains);
    }

    /** Répartit les points gagnés entre les héros, au prorata de leur statistique utile. */
    private function repartirContribution(MissionSimulationState $etat, array $vivants, ObjectifMission $objectif, float $gain, \DateTimeImmutable $t): void
    {
        $poids = [];
        foreach ($vivants as $hero) {
            $stats = $this->statsResolver->statsEffectives($hero, $t);
            $poids[(int) $hero->getId()] = max(1, null !== $objectif->getStatistiqueCle() ? $stats[$objectif->getStatistiqueCle()->value] ?? 0 : (int) round(array_sum($stats) / 5));
        }
        $total = array_sum($poids);
        foreach ($poids as $heroId => $p) {
            $etat->ajouterContribution($heroId, $gain * $p / $total);
        }
    }

    private function terminer(Mission $mission, MissionSimulationState $etat, bool $succes, string $motif, \DateTimeImmutable $now, int $etapes = 0): RapportSimulation
    {
        $etat->terminer($succes ? 'SUCCES' : 'ECHEC', $now);
        $etat->journaliser($now, $succes ? 'ok' : 'danger', sprintf('Mission %s : %s.', $succes ? 'réussie' : 'échouée', $motif));
        $etat->setTauxReussiteDirect($succes ? 100 : 0);
        $mission->setTauxDeReussite($succes ? 100 : 0);

        $contributions = $etat->getContributions();
        $total = array_sum($contributions);
        foreach ($mission->getAffectationsActives() as $affectation) {
            if ($total > 0) {
                $affectation->setContribution((int) round(100 * ($contributions[(string) $affectation->getHero()->getId()] ?? 0) / $total));
            }
        }

        $equipe = $mission->getHerosAffectes();
        $this->mouvements->interrompreMission($mission, $now);
        $this->alerter($mission, $now, $succes ? 'Mission réussie' : 'Mission échouée', sprintf('%s — %s', $mission->getNom(), $motif), $succes ? NiveauAlerte::INFO : NiveauAlerte::ALERTE, 'FIN', 0);
        $this->cycle->cloturer($mission, $succes);
        // Fin de mission : les héros épuisés ou en état critique sont pris en charge.
        foreach ($equipe as $hero) {
            $this->rapatriement?->rapatrierSiNecessaire($hero, $now, TypeAdmission::POST_MISSION, $mission);
        }

        return new RapportSimulation((int) $mission->getId(), $succes ? self::PHASE_TERMINEE : self::PHASE_ECHOUEE, $etapes, true, $succes, [$motif]);
    }

    public function phase(Mission $mission, ?MissionSimulationState $etat): string
    {
        return match (true) {
            MissionStatut::TERMINEE === $mission->getStatut() => self::PHASE_TERMINEE,
            \in_array($mission->getStatut(), [MissionStatut::ECHOUEE, MissionStatut::ANNULEE], true) => self::PHASE_ECHOUEE,
            MissionStatut::EN_COURS !== $mission->getStatut() => self::PHASE_PLANIFIEE,
            null !== $etat?->getDateEngagement() => self::PHASE_ENGAGEMENT,
            default => self::PHASE_APPROCHE,
        };
    }

    /** Progression moyenne (décimale) des objectifs obligatoires. */
    public function progressionObligatoire(Mission $mission, ?MissionSimulationState $etat): float
    {
        $obligatoires = $mission->getObjectifsObligatoires();
        if ([] === $obligatoires) {
            return 0.0;
        }
        $total = 0.0;
        foreach ($obligatoires as $objectif) {
            $total += $objectif->isTermine() ? 100.0 : (null !== $etat && null !== $objectif->getId()
                ? $etat->getProgressionPrecise($objectif->getId(), $objectif->getProgression())
                : $objectif->getProgression());
        }

        return $total / \count($obligatoires);
    }

    /** @return list<Hero> héros arrivés sur place et encore en état de contribuer */
    public function herosOperationnels(Mission $mission, ?\DateTimeImmutable $a = null): array
    {
        $heros = [];
        foreach ($mission->getAffectationsActives() as $affectation) {
            if (null !== $a && null !== $affectation->getDateArrivee() && $affectation->getDateArrivee() > $a) {
                continue;
            }
            if (AffectationStatut::SUR_PLACE === $affectation->getStatut() && $this->sante->peutContribuer($affectation->getHero())) {
                $heros[] = $affectation->getHero();
            }
        }

        return $heros;
    }

    private function nombreEnRoute(Mission $mission): int
    {
        return \count(array_filter($mission->getAffectationsActives(), fn (AffectationMission $a) => AffectationStatut::AFFECTE === $a->getStatut() && $this->sante->peutContribuer($a->getHero())));
    }

    private function etat(Mission $mission, \DateTimeImmutable $now): MissionSimulationState
    {
        $etat = $this->etats->findPourMission($mission);
        if (null === $etat) {
            $etat = new MissionSimulationState($mission, $mission->getDateDebut() ?? $now);
            $this->em->persist($etat);
        }

        return $etat;
    }

    private function dateEngagement(Mission $mission, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $premiere = null;
        foreach ($mission->getAffectationsActives() as $affectation) {
            $arrivee = $affectation->getDateArrivee();
            if (AffectationStatut::SUR_PLACE === $affectation->getStatut() && null !== $arrivee && (null === $premiere || $arrivee < $premiere)) {
                $premiere = $arrivee;
            }
        }

        return null === $premiere || $premiere > $now ? $now : $premiere;
    }

    /** Une mission sans objectif reçoit un objectif obligatoire par défaut pour pouvoir aboutir. */
    private function garantirObjectif(Mission $mission): void
    {
        if ($mission->getObjectifs()->count() > 0) {
            return;
        }
        $objectif = (new ObjectifMission())->setTitre(self::OBJECTIF_PAR_DEFAUT)->setOrdreAffichage(1)->setObligatoire(true);
        $mission->addObjectif($objectif);
        $this->em->persist($objectif);
        $this->em->flush();
    }

    private function echeanceDepassee(Mission $mission, \DateTimeImmutable $now): bool
    {
        return null !== $mission->getDateFinPrevue() && $mission->getDateFinPrevue() <= $now;
    }

    private function alerter(Mission $mission, \DateTimeImmutable $t, string $titre, string $message, NiveauAlerte $niveau, string $prefixe, int $suffixe): void
    {
        $alerte = new Alerte($titre, $message, $niveau, sprintf('%s-%s-%d', $prefixe, $t->format('His'), $suffixe ?: (int) $mission->getId()), $t);
        $alerte->setMission($mission);
        $this->em->persist($alerte);
    }

    private function secondes(\DateTimeImmutable $de, \DateTimeImmutable $a): float
    {
        return (float) $a->format('U.u') - (float) $de->format('U.u');
    }

    private function ajouter(\DateTimeImmutable $date, float $secondes): \DateTimeImmutable
    {
        $micro = (int) round($secondes * 1_000_000);

        return $date->modify(sprintf('%+d microseconds', $micro));
    }
}
