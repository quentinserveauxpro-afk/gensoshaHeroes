<?php

namespace App\Infirmerie;

use App\Entity\AffectationMission;
use App\Entity\Hero;
use App\Entity\Mission;
use App\Entity\MissionSimulationState;
use App\Entity\SejourInfirmerie;
use App\Entity\User;
use App\Enum\AffectationStatut;
use App\Enum\MissionStatut;
use App\Enum\MotifInfirmerie;
use App\Enum\NiveauAlerte;
use App\Enum\TypeAdmission;
use App\Geo\Coordonnees;
use App\Geo\DistanceCalculator;
use App\Geo\EstimatedArrivalCalculator;
use App\Movement\HeroMovementService;
use App\Repository\HeroMovementRepository;
use App\Repository\HeroRepository;
use App\Repository\MissionSimulationStateRepository;
use App\Repository\SejourInfirmerieRepository;
use App\Service\Mission\MissionSuccessCalculator;
use App\Service\Mission\SynergyResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Admission à l'infirmerie (automatique, manuelle ou après mission) et rapatriement au QG.
 *
 * Conséquences d'une admission :
 *  1. création d'un séjour (motif, état initial) — un seul séjour actif par héros, garanti
 *     par un contrôle applicatif puis par l'index unique de la colonne cle_active ;
 *  2. l'affectation active passe à RETIRE (jamais supprimée) et sa contribution est figée :
 *     le héros ne contribue plus aux objectifs, la mission continue avec le reste de l'équipe ;
 *  3. son déplacement vers la mission est interrompu, son retour vers le QG commence ;
 *  4. le statut héros existant BLESSE (ou REPOS pour l'épuisement) le rend non déployable ;
 *  5. taux de réussite recalculé, aptitudes obligatoires désormais manquantes signalées,
 *     alertes émises pour les opérateurs.
 *
 * Appelée par le moteur de mission, l'écriture est validée par le flush transactionnel du
 * passage (sous verrou de mission) ; appelée par un opérateur, admettreManuellement() ouvre
 * sa propre transaction et verrouille la ligne du héros (SELECT … FOR UPDATE).
 */
final class HeroRepatriationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SejourInfirmerieRepository $sejours,
        private readonly HeroRepository $heroes,
        private readonly HeroMovementRepository $mouvements,
        private readonly HeroMovementService $deplacements,
        private readonly MissionSimulationStateRepository $etats,
        private readonly MissionSuccessCalculator $calculateur,
        private readonly SynergyResolver $synergies,
        private readonly HeroMedicalEvaluationService $medical,
        private readonly HeroRecoveryService $recuperation,
        private readonly InfirmerieAlerteService $alertes,
        private readonly InfirmerieConfiguration $config,
        private readonly QgConfiguration $qg,
        private readonly DistanceCalculator $distances,
        private readonly EstimatedArrivalCalculator $eta,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Séjour actif du héros, y compris un séjour créé dans ce passage et pas encore enregistré. */
    public function sejourActif(Hero $hero): ?SejourInfirmerie
    {
        foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $entite) {
            if ($entite instanceof SejourInfirmerie && $entite->getHero() === $hero && $entite->isActif()) {
                return $entite;
            }
        }

        return null === $hero->getId() ? null : $this->sejours->findActifPourHero($hero);
    }

    /**
     * Rapatriement automatique si l'état du héros l'impose (idempotent : rien si déjà admis).
     */
    public function rapatrierSiNecessaire(Hero $hero, ?\DateTimeImmutable $t = null, TypeAdmission $type = TypeAdmission::AUTOMATIQUE, ?Mission $mission = null): ?SejourInfirmerie
    {
        $motif = $this->medical->motifRapatriement($hero);
        if (null === $motif || null !== $this->sejourActif($hero)) {
            return null;
        }

        return $this->admettre($hero, $motif, $type, $t ?? $this->clock->now(), null, null, $mission);
    }

    /**
     * Admission manuelle par un opérateur, dans une transaction, ligne du héros verrouillée.
     *
     * @throws InfirmerieException
     */
    public function admettreManuellement(Hero $hero, MotifInfirmerie $motif, ?User $operateur, ?string $notes): SejourInfirmerie
    {
        return $this->em->wrapInTransaction(function () use ($hero, $motif, $operateur, $notes): SejourInfirmerie {
            $verrouille = $this->heroes->findPourMiseAJour([(int) $hero->getId()])[0] ?? throw new InfirmerieException('Héros introuvable.');
            $this->em->refresh($verrouille);

            return $this->admettre($verrouille, $motif, TypeAdmission::MANUELLE, $this->clock->now(), $operateur, $notes);
        });
    }

    /** @throws InfirmerieException si le héros a déjà un séjour actif */
    public function admettre(Hero $hero, MotifInfirmerie $motif, TypeAdmission $type, \DateTimeImmutable $t, ?User $operateur = null, ?string $notes = null, ?Mission $missionOrigine = null): SejourInfirmerie
    {
        if (null !== $this->sejourActif($hero)) {
            throw new InfirmerieException(sprintf('%s est déjà pris en charge par l\'infirmerie.', $hero->getNom()));
        }

        $affectation = $hero->getAffectationActive();
        $mission = $affectation?->getMission() ?? $missionOrigine;
        $position = $this->positionActuelle($hero, $affectation, $t);

        $sejour = new SejourInfirmerie($hero, $motif, $type, $this->medical->santeCible($hero), $this->medical->fatigueCible(), $t);
        $sejour->setMission($mission)->setDemandePar($operateur);
        $notes = null !== $notes ? trim($notes) : '';
        if ('' !== $notes) {
            $sejour->setNotes(mb_substr($notes, 0, 2000));
        }
        $this->em->persist($sejour);

        if (null !== $affectation) {
            $this->retirerDeMission($affectation, $sejour, $t);
        }
        $hero->setStatut($motif->statutHero());

        $this->alerterAdmission($sejour, $type, $t);
        $this->demarrerRetour($sejour, $position, $t);

        return $sejour;
    }

    /** Aperçu des conséquences du retrait de mission (sans aucune écriture). */
    public function consequences(Hero $hero): ConsequencesRetrait
    {
        $affectation = $hero->getAffectationActive();
        $mission = $affectation?->getMission();
        if (null === $mission) {
            return new ConsequencesRetrait(null);
        }
        $avant = $mission->getHerosAffectes();
        $apres = array_values(array_filter($avant, static fn (Hero $h) => $h !== $hero));
        $nomsApres = array_map(static fn ($s) => (string) $s->getNom(), $this->synergies->synergiesActives($apres, $mission));
        $perdues = array_values(array_diff(array_map(static fn ($s) => (string) $s->getNom(), $this->synergies->synergiesActives($avant, $mission)), $nomsApres));

        return new ConsequencesRetrait(
            $mission,
            array_map(static fn (Hero $h) => (string) $h->getNom(), $apres),
            (int) $mission->getTauxDeReussite(),
            [] === $apres ? 0 : $this->calculateur->taux($mission, $apres),
            $perdues,
            $this->aptitudesNouvellementManquantes($mission, $avant, $apres),
        );
    }

    private function retirerDeMission(AffectationMission $affectation, SejourInfirmerie $sejour, \DateTimeImmutable $t): void
    {
        $hero = $affectation->getHero();
        $mission = $affectation->getMission();
        $avant = $mission->getHerosAffectes();

        $etat = $this->etatSimulation($mission);
        $contributions = $etat?->getContributions() ?? [];
        $total = array_sum($contributions);
        if ($total > 0) {
            $affectation->setContribution((int) round(100 * ($contributions[(string) $hero->getId()] ?? 0) / $total));
        }
        $affectation->setStatut(AffectationStatut::RETIRE);

        $mouvement = $this->mouvements->findDernierPourHeroEtMission($hero, $mission);
        if (null !== $mouvement && $mouvement->isEnCours()) {
            $mouvement->interrompre($t);
        }

        $apres = array_values(array_filter($avant, static fn (Hero $h) => $h !== $hero));
        $manquantes = $this->aptitudesNouvellementManquantes($mission, $avant, $apres);
        $etat?->journaliser($t, 'danger', sprintf('%s rapatrié au QG (%s).', $hero->getNom(), mb_strtolower($sejour->getMotif()->label())));
        if ([] !== $manquantes) {
            $etat?->journaliser($t, 'warn', 'Compétences obligatoires désormais manquantes : '.implode(', ', $manquantes).'.');
            $this->alertes->pourSejour($sejour, InfirmerieAlerteService::COMPETENCE, 'Compétence manquante',
                sprintf('« %s » : %s ne sont plus couvertes après le retrait de %s.', $mission->getNom(), implode(', ', $manquantes), $hero->getNom()), NiveauAlerte::ALERTE, $t);
        }
        if (MissionStatut::EN_COURS === $mission->getStatut()) {
            // Recalcul immédiat (le moteur affinera au passage suivant avec la progression réelle).
            $mission->setTauxDeReussite([] === $apres ? 0 : min((int) $mission->getTauxDeReussite(), $this->calculateur->taux($mission, $apres)));
        }
    }

    /** État de simulation de la mission, y compris s'il vient d'être créé dans ce passage. */
    private function etatSimulation(Mission $mission): ?MissionSimulationState
    {
        foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $entite) {
            if ($entite instanceof MissionSimulationState && $entite->getMission() === $mission) {
                return $entite;
            }
        }

        return null === $mission->getId() ? null : $this->etats->findPourMission($mission);
    }

    /**
     * @param list<Hero> $avant
     * @param list<Hero> $apres
     *
     * @return list<string>
     */
    private function aptitudesNouvellementManquantes(Mission $mission, array $avant, array $apres): array
    {
        $noms = static fn (array $aptitudes) => array_map(static fn ($a) => (string) $a->getNom(), $aptitudes);

        return array_values(array_diff($noms($this->calculateur->aptitudesManquantes($mission, $apres)), $noms($this->calculateur->aptitudesManquantes($mission, $avant))));
    }

    private function demarrerRetour(SejourInfirmerie $sejour, ?Coordonnees $depart, \DateTimeImmutable $t): void
    {
        $distance = null === $depart ? 0.0 : $this->distances->distanceKm($depart, $this->qg->coordonnees());
        if (null === $depart || $distance <= QgConfiguration::RAYON_KM) {
            // Déjà au QG (ou position inconnue : aucune position n'est inventée) → traitement immédiat.
            $this->recuperation->arriver($sejour, HeroRecoveryService::secondeEntiere($t));

            return;
        }
        $vitesse = $this->eta->vitessePourHero($sejour->getHero());
        $sejour->commencerRetour($depart, round($distance, 3), $vitesse, $t, $this->eta->dateArrivee($t, $distance, $vitesse));
    }

    /** Position réelle à l'instant t : sur son trajet s'il était en route, sinon sa dernière position. */
    private function positionActuelle(Hero $hero, ?AffectationMission $affectation, \DateTimeImmutable $t): ?Coordonnees
    {
        $mission = $affectation?->getMission();
        if (null !== $mission) {
            $mouvement = $this->mouvements->findDernierPourHeroEtMission($hero, $mission);
            if (null !== $mouvement && $mouvement->isEnCours()) {
                return $this->deplacements->etatA($mouvement, $t)->position;
            }
        }

        return $hero->getCoordonnees();
    }

    private function alerterAdmission(SejourInfirmerie $sejour, TypeAdmission $type, \DateTimeImmutable $t): void
    {
        $hero = $sejour->getHero();
        $etat = sprintf('santé %d %%, fatigue %d %%', (int) round($this->medical->pourcentage($hero)), $hero->getFatigue());
        if (MotifInfirmerie::SANTE_CRITIQUE === $sejour->getMotif()) {
            $this->alertes->pourSejour($sejour, InfirmerieAlerteService::CRITIQUE, 'Santé critique', sprintf('%s : %s.', $hero->getNom(), $etat), NiveauAlerte::CRITIQUE, $t);
        } elseif (MotifInfirmerie::EPUISEMENT === $sejour->getMotif()) {
            $this->alertes->pourSejour($sejour, InfirmerieAlerteService::CRITIQUE, 'Épuisement critique', sprintf('%s : %s.', $hero->getNom(), $etat), NiveauAlerte::ALERTE, $t);
        }
        $origine = null !== $sejour->getMission() ? sprintf(' depuis « %s »', $sejour->getMission()->getNom()) : '';
        $this->alertes->pourSejour($sejour, InfirmerieAlerteService::RAPATRIEMENT, 'Rapatriement déclenché',
            sprintf('%s rapatrié au QG%s — %s (%s).', $hero->getNom(), $origine, $sejour->getMotif()->label(), mb_strtolower($type->label())),
            TypeAdmission::MANUELLE === $type ? NiveauAlerte::INFO : NiveauAlerte::ALERTE, $t);
    }
}
