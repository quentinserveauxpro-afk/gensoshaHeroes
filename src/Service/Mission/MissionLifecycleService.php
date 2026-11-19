<?php

namespace App\Service\Mission;

use App\Entity\Mission;
use App\Enum\AffectationStatut;
use App\Enum\HeroStatut;
use App\Enum\MissionStatut;
use App\Service\Alerte\AlerteService;
use App\Service\Hero\HeroProgressionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Transitions de statut d'une mission :
 *  BROUILLON → EN_ATTENTE (publication) ;
 *  EN_ATTENTE → EN_COURS (lancement, équipe déjà affectée) ;
 *  EN_COURS → TERMINEE | ECHOUEE (clôture) ;
 *  tout statut non clos → ANNULEE.
 */
final class MissionLifecycleService
{
    /** En dessous de ce pourcentage de PV, un héros libéré passe au statut BLESSE. */
    public const SEUIL_BLESSURE = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HeroProgressionService $progression,
        private readonly MissionSuccessCalculator $calculator,
        private readonly AlerteService $alertes,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Enregistre une nouvelle mission et déclenche une alerte si elle est critique. */
    public function creer(Mission $mission): void
    {
        $mission->setTauxDeReussite($mission->getTauxDeReussite() ?? 0);
        $this->em->persist($mission);
        $this->alertes->pourMission($mission);
        $this->em->flush();
    }

    public function publier(Mission $mission): void
    {
        if (MissionStatut::BROUILLON !== $mission->getStatut()) {
            throw new MissionWorkflowException('Seule une mission en brouillon peut être publiée.');
        }
        $mission->setStatut(MissionStatut::EN_ATTENTE);
        $this->em->flush();
    }

    public function lancer(Mission $mission): void
    {
        if (!\in_array($mission->getStatut(), [MissionStatut::BROUILLON, MissionStatut::EN_ATTENTE], true)) {
            throw new MissionWorkflowException('Cette mission ne peut pas être lancée dans son état actuel.');
        }
        $equipe = $mission->getHerosAffectes();
        if (\count($equipe) < $mission->getTailleEquipeMinimale()) {
            throw new MissionWorkflowException('Constituez d\'abord une équipe suffisante via le Squad Builder.');
        }
        $now = $this->clock->now();
        $mission->setStatut(MissionStatut::EN_COURS);
        $mission->setDateDebut($mission->getDateDebut() ?? $now);
        $mission->setTauxDeReussite($this->calculator->taux($mission, $equipe));
        $this->em->flush();
    }

    public function cloturer(Mission $mission, bool $succes): void
    {
        if (MissionStatut::EN_COURS !== $mission->getStatut()) {
            throw new MissionWorkflowException('Seule une mission en cours peut être clôturée.');
        }
        $mission->setStatut($succes ? MissionStatut::TERMINEE : MissionStatut::ECHOUEE);
        $mission->setDateFinReelle($this->clock->now());

        $actives = $mission->getAffectationsActives();
        $xp = (int) round(($mission->getDifficulte()?->experienceRecompense() ?? 200) * ($succes ? 1 : 0.3));
        foreach ($actives as $affectation) {
            $affectation->setStatut(AffectationStatut::TERMINEE);
            $affectation->setContribution($affectation->getContribution() ?? (int) round(100 / max(1, \count($actives))));
            $hero = $affectation->getHero();
            $this->progression->ajouterExperience($hero, $xp);
            $hero->setFatigue($hero->getFatigue() + 15);
        }
        $this->libererHeros($mission);
        $this->em->flush();
    }

    public function annuler(Mission $mission): void
    {
        if ($mission->getStatut()->isClosed()) {
            throw new MissionWorkflowException('La mission est déjà close.');
        }
        foreach ($mission->getAffectationsActives() as $affectation) {
            $affectation->setStatut(AffectationStatut::RETIRE);
        }
        $this->libererHeros($mission);
        $mission->setStatut(MissionStatut::ANNULEE);
        $mission->setDateFinReelle($this->clock->now());
        $this->em->flush();
    }

    private function libererHeros(Mission $mission): void
    {
        foreach ($mission->getAffectations() as $affectation) {
            $hero = $affectation->getHero();
            if (HeroStatut::EN_MISSION !== $hero->getStatut() || null !== $hero->getAffectationActive()) {
                continue;
            }
            $hero->setStatut($hero->getSantePourcentage() < self::SEUIL_BLESSURE ? HeroStatut::BLESSE : HeroStatut::DISPONIBLE);
        }
    }
}
