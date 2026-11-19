<?php

namespace App\Tests\Integration;

use App\Entity\AffectationMission;
use App\Entity\Hero;
use App\Entity\Mission;
use App\Enum\AffectationStatut;
use App\Enum\HeroStatut;
use App\Enum\MissionStatut;
use App\Enum\RoleEquipe;
use App\Service\Mission\DeploymentException;
use App\Service\Mission\MissionDeploymentService;
use App\Tests\Support\Builder;
use App\Tests\Support\ResetDatabaseTrait;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MissionDeploymentServiceTest extends KernelTestCase
{
    use ResetDatabaseTrait;

    private EntityManagerInterface $em;
    private MissionDeploymentService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(MissionDeploymentService::class);
        self::viderBase($this->em);
    }

    public function testDeploiementCreeLesAffectationsEtChangeLesStatuts(): void
    {
        $mission = Builder::mission(['min' => 2, 'max' => 3]);
        [$a, $b] = [Builder::hero(['nom' => 'Apex']), Builder::hero(['nom' => 'Valkyrie'])];
        self::persister($this->em, $mission, $a, $b);

        $affectations = $this->service->deployer($mission, [$a->getId(), $b->getId()]);
        $this->em->clear();

        $mission = $this->em->find(Mission::class, $mission->getId());
        self::assertCount(2, $affectations);
        self::assertSame(MissionStatut::EN_COURS, $mission->getStatut());
        self::assertNotNull($mission->getDateDebut());
        self::assertGreaterThan(0, $mission->getTauxDeReussite());
        self::assertCount(2, $mission->getAffectationsActives());
        foreach ($mission->getAffectations() as $affectation) {
            self::assertSame(HeroStatut::EN_MISSION, $affectation->getHero()->getStatut());
            self::assertSame(AffectationStatut::AFFECTE, $affectation->getStatut());
            self::assertSame(100, $affectation->getPointsDeVieAuDepart());
        }
        $lead = array_filter($mission->getAffectationsActives(), static fn (AffectationMission $x) => RoleEquipe::LEAD === $x->getRole());
        self::assertSame('Apex', array_values($lead)[0]->getHero()->getNom());
    }

    public function testTransactionAnnuleeSiUnHerosEstInvalide(): void
    {
        $mission = Builder::mission(['min' => 2, 'max' => 3]);
        $ok = Builder::hero();
        $blesse = Builder::hero(['statut' => HeroStatut::BLESSE]);
        self::persister($this->em, $mission, $ok, $blesse);

        try {
            $this->service->deployer($mission, [$ok->getId(), $blesse->getId()]);
            self::fail('Le déploiement aurait dû échouer.');
        } catch (DeploymentException $e) {
            self::assertStringContainsString('indisponible', $e->getMessage());
        }

        $this->em->clear();
        self::assertSame(0, $this->em->getRepository(AffectationMission::class)->count([]));
        self::assertSame(HeroStatut::DISPONIBLE, $this->em->find(Hero::class, $ok->getId())->getStatut());
        self::assertSame(MissionStatut::EN_ATTENTE, $this->em->find(Mission::class, $mission->getId())->getStatut());
        self::assertTrue($this->em->isOpen(), 'L\'EntityManager doit rester utilisable après un refus métier.');
    }

    public function testUnHerosNePeutPasEtreAffecteADeuxMissionsActives(): void
    {
        $m1 = Builder::mission(['nom' => 'Réacteur']);
        $m2 = Builder::mission(['nom' => 'Métro']);
        $titan = Builder::hero(['nom' => 'Titan']);
        self::persister($this->em, $m1, $m2, $titan);

        $this->service->deployer($m1, [$titan->getId()]);

        $this->expectException(DeploymentException::class);
        $this->expectExceptionMessageMatches('/Titan est déjà engagé dans la mission « Réacteur »/');
        $this->service->deployer($m2, [$titan->getId()]);
    }

    public function testLeHerosEstVerrouillePendantLeDeploiement(): void
    {
        $mission = Builder::mission();
        $hero = Builder::hero();
        self::persister($this->em, $mission, $hero);

        // Un « second opérateur » ouvre une transaction et verrouille la ligne du héros.
        $params = $this->em->getConnection()->getParams();
        $autre = \Doctrine\DBAL\DriverManager::getConnection($params);
        $autre->beginTransaction();
        $autre->fetchOne('SELECT id FROM hero WHERE id = ? FOR UPDATE', [$hero->getId()]);

        $this->em->getConnection()->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            $this->service->deployer($mission, [$hero->getId()]);
            self::fail('Le déploiement aurait dû attendre le verrou.');
        } catch (LockWaitTimeoutException) {
            self::addToAssertionCount(1);
        } finally {
            $autre->rollBack();
            $autre->close();
        }

        $this->em->clear();
        self::assertSame(0, $this->em->getRepository(AffectationMission::class)->count([]));
        // Une fois le verrou libéré, le déploiement réussit.
        $this->service->deployer($this->em->find(Mission::class, $mission->getId()), [$hero->getId()]);
        self::assertSame(1, $this->em->getRepository(AffectationMission::class)->count([]));
    }
}
