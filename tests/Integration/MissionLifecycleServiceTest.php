<?php

namespace App\Tests\Integration;

use App\Enum\AffectationStatut;
use App\Enum\Difficulte;
use App\Enum\HeroStatut;
use App\Enum\MissionStatut;
use App\Enum\NiveauUrgence;
use App\Entity\Alerte;
use App\Service\Mission\MissionDeploymentService;
use App\Service\Mission\MissionLifecycleService;
use App\Service\Mission\MissionWorkflowException;
use App\Tests\Support\Builder;
use App\Tests\Support\ResetDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MissionLifecycleServiceTest extends KernelTestCase
{
    use ResetDatabaseTrait;

    private EntityManagerInterface $em;
    private MissionLifecycleService $cycle;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->cycle = self::getContainer()->get(MissionLifecycleService::class);
        self::viderBase($this->em);
    }

    public function testClotureReussieLibereLesHerosEtDonneDeLExperience(): void
    {
        $mission = Builder::mission(['difficulte' => Difficulte::B]);
        $hero = Builder::hero(['niveau' => 10, 'xp' => 0]);
        self::persister($this->em, $mission, $hero);
        self::getContainer()->get(MissionDeploymentService::class)->deployer($mission, [$hero->getId()]);

        $this->cycle->cloturer($mission, true);

        self::assertSame(MissionStatut::TERMINEE, $mission->getStatut());
        self::assertNotNull($mission->getDateFinReelle());
        self::assertSame(HeroStatut::DISPONIBLE, $hero->getStatut());
        self::assertSame(800, $hero->getXp());
        self::assertSame(AffectationStatut::TERMINEE, $mission->getAffectations()->first()->getStatut());
        self::assertSame(100, $mission->getAffectations()->first()->getContribution());
    }

    public function testAnnulationEtTransitionsInterdites(): void
    {
        $mission = Builder::mission();
        $hero = Builder::hero();
        self::persister($this->em, $mission, $hero);
        self::getContainer()->get(MissionDeploymentService::class)->deployer($mission, [$hero->getId()]);

        $this->cycle->annuler($mission);
        self::assertSame(MissionStatut::ANNULEE, $mission->getStatut());
        self::assertSame(HeroStatut::DISPONIBLE, $hero->getStatut());
        self::assertSame(AffectationStatut::RETIRE, $mission->getAffectations()->first()->getStatut());

        $this->expectException(MissionWorkflowException::class);
        $this->cycle->cloturer($mission, true);
    }

    public function testLancementExigeUneEquipe(): void
    {
        $mission = Builder::mission(['min' => 2]);
        self::persister($this->em, $mission);

        $this->expectException(MissionWorkflowException::class);
        $this->cycle->lancer($mission);
    }

    public function testUneMissionCritiqueCreeeGenereUneAlertePersistee(): void
    {
        $this->cycle->creer(Builder::mission(['urgence' => NiveauUrgence::CRITIQUE, 'statut' => MissionStatut::BROUILLON]));
        $this->cycle->creer(Builder::mission(['urgence' => NiveauUrgence::FAIBLE, 'statut' => MissionStatut::BROUILLON]));

        self::assertSame(1, $this->em->getRepository(Alerte::class)->count([]));
    }
}
