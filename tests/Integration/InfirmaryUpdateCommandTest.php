<?php

namespace App\Tests\Integration;

use App\Entity\AffectationMission;
use App\Entity\Alerte;
use App\Entity\GeoPosition;
use App\Entity\Hero;
use App\Entity\Mission;
use App\Entity\MissionSimulationState;
use App\Entity\ObjectifMission;
use App\Entity\SejourInfirmerie;
use App\Enum\AffectationStatut;
use App\Enum\HeroStatut;
use App\Enum\MissionStatut;
use App\Enum\MotifInfirmerie;
use App\Enum\StatutSejour;
use App\Enum\TypeAdmission;
use App\Infirmerie\HeroRepatriationService;
use App\Infirmerie\InfirmaryUpdateRunner;
use App\Infirmerie\InfirmerieException;
use App\Infirmerie\QgConfiguration;
use App\Simulation\MissionSimulationService;
use App\Simulation\MissionUpdateRunner;
use App\Tests\Support\Builder;
use App\Tests\Support\ResetDatabaseTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;

/** Infirmerie : rapatriement, retrait de mission, retour au QG, récupération, verrous. */
final class InfirmaryUpdateCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use ResetDatabaseTrait;

    private EntityManagerInterface $em;
    private MockClock $horloge;
    private int $missionId;
    private int $blesseId;
    private int $coequipierId;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->horloge = self::mockTime('2026-11-20 10:00:00');
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::viderBase($this->em);

        $now = new \DateTimeImmutable('2026-11-20 10:00:00');
        $vol = Builder::aptitude('VOL');
        $mission = Builder::mission(['statut' => MissionStatut::EN_COURS, 'fin' => $now->modify('+3 hours')]);
        $mission->setGeoPosition(GeoPosition::en(45.7640, 4.8357))->setDateDebut($now)->addAptitudeRequise($vol);
        $mission->addObjectif((new ObjectifMission())->setTitre('Sécuriser la zone')->setObligatoire(true)->setOrdreAffichage(1));
        $blesse = Builder::hero(['nom' => 'Blessé', 'pv' => 15, 'pvMax' => 100, 'aptitudes' => [$vol], 'statut' => HeroStatut::EN_MISSION]);
        $blesse->setGeoPosition(GeoPosition::en(45.7640, 4.8357));
        $coequipier = Builder::hero(['nom' => 'Coéquipier', 'pv' => 100, 'pvMax' => 100, 'statut' => HeroStatut::EN_MISSION]);
        $coequipier->setGeoPosition(GeoPosition::en(45.7640, 4.8357));
        self::persister($this->em, $vol, $mission, $blesse, $coequipier);
        foreach ([$blesse, $coequipier] as $hero) {
            self::persister($this->em, (new AffectationMission($hero, $mission, $now))->setStatut(AffectationStatut::SUR_PLACE)->setDateArrivee($now));
        }
        $this->missionId = (int) $mission->getId();
        $this->blesseId = (int) $blesse->getId();
        $this->coequipierId = (int) $coequipier->getId();
        $this->em->clear();
    }

    private function executer(string $commande = 'app:infirmary:update', array $options = []): string
    {
        $tester = new CommandTester((new Application(self::$kernel))->find($commande));
        self::assertSame(0, $tester->execute($options));
        $this->em->clear();

        return $tester->getDisplay();
    }

    private function sejour(): ?SejourInfirmerie
    {
        return $this->em->getRepository(SejourInfirmerie::class)->findOneBy([]);
    }

    /** @return list<string> */
    private function titresAlertes(): array
    {
        return array_map(static fn (Alerte $a) => $a->getTitre(), $this->em->getRepository(Alerte::class)->findBy([], ['id' => 'ASC']));
    }

    public function testRapatriementAutomatiqueRetireLeHerosSansEffacerLHistorique(): void
    {
        self::assertStringContainsString('1 admission(s)', $this->executer());

        $sejour = $this->sejour();
        self::assertSame($this->blesseId, $sejour->getHero()->getId());
        self::assertSame(MotifInfirmerie::SANTE_CRITIQUE, $sejour->getMotif());
        self::assertSame(TypeAdmission::AUTOMATIQUE, $sejour->getTypeAdmission());
        self::assertSame(StatutSejour::RETOUR_QG, $sejour->getStatut(), 'Lyon → Paris : retour en cours');
        self::assertSame(15, $sejour->getSanteAdmission());
        self::assertSame($this->missionId, $sejour->getMission()->getId());
        self::assertEqualsWithDelta(392, $sejour->getDistanceRetourKm(), 5);

        // Historique conservé : l'affectation passe à RETIRE, elle n'est pas supprimée.
        $affectations = $this->em->getRepository(AffectationMission::class)->findBy(['hero' => $this->blesseId]);
        self::assertCount(1, $affectations);
        self::assertSame(AffectationStatut::RETIRE, $affectations[0]->getStatut());
        self::assertSame(HeroStatut::BLESSE, $this->em->find(Hero::class, $this->blesseId)->getStatut());

        // La mission continue avec le coéquipier seul ; le blessé ne contribue plus.
        $mission = $this->em->find(Mission::class, $this->missionId);
        self::assertSame(MissionStatut::EN_COURS, $mission->getStatut());
        $operationnels = static::getContainer()->get(MissionSimulationService::class)->herosOperationnels($mission);
        self::assertSame([$this->coequipierId], array_map(static fn (Hero $h) => $h->getId(), $operationnels));

        self::assertEqualsCanonicalizing(['Santé critique', 'Compétence manquante', 'Rapatriement déclenché'], $this->titresAlertes());

        // Relance : aucune nouvelle admission ni alerte.
        self::assertStringContainsString('0 admission(s)', $this->executer());
        self::assertCount(1, $this->em->getRepository(SejourInfirmerie::class)->findAll());
        self::assertCount(3, $this->titresAlertes());
    }

    public function testUnSeulSejourActifParHeros(): void
    {
        $hero = $this->em->find(Hero::class, $this->blesseId);
        $service = static::getContainer()->get(HeroRepatriationService::class);
        $service->admettre($hero, MotifInfirmerie::BLESSURE, TypeAdmission::MANUELLE, $this->horloge->now());
        $this->em->flush();

        try {
            $service->admettre($hero, MotifInfirmerie::DECISION_MEDICALE, TypeAdmission::MANUELLE, $this->horloge->now());
            self::fail('Deuxième séjour actif accepté.');
        } catch (InfirmerieException) {
        }
        self::assertNull($service->rapatrierSiNecessaire($hero), 'rapatriement automatique sans effet');

        // Dernier rempart : l'index unique refuse un second séjour actif écrit directement.
        $this->em->persist(new SejourInfirmerie($hero, MotifInfirmerie::BLESSURE, TypeAdmission::MANUELLE, 100, 20, $this->horloge->now()));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    public function testCycleCompletRetourTraitementRetablissementSortie(): void
    {
        $this->executer();
        $arrivee = $this->sejour()->getArriveeQgPrevueLe();

        // Pendant le retour : aucune récupération.
        $this->horloge->sleep(60);
        $this->executer();
        self::assertSame(StatutSejour::RETOUR_QG, $this->sejour()->getStatut());
        self::assertEqualsWithDelta(15.0, $this->sejour()->getSanteActuelle(), 1e-9);

        // Arrivée au QG : traitement, héros repositionné sur le QG.
        $this->horloge->sleep($arrivee->getTimestamp() - $this->horloge->now()->getTimestamp() + 30);
        self::assertStringContainsString('1 arrivée(s)', $this->executer());
        $sejour = $this->sejour();
        self::assertSame(StatutSejour::EN_TRAITEMENT, $sejour->getStatut());
        self::assertEquals($arrivee, $sejour->getDebutTraitementLe());
        self::assertTrue((new QgConfiguration())->coordonnees()->egale($sejour->getHero()->getCoordonnees(), 1e-5));
        self::assertGreaterThan(15.0, $sejour->getSanteActuelle(), '30 s de traitement');

        // Longue interruption du worker : rétablissement daté exactement, sortie à valider.
        $this->horloge->sleep(5 * 86400);
        self::assertStringContainsString('1 rétabli(s), 0 sortie(s)', $this->executer());
        $sejour = $this->sejour();
        self::assertSame(StatutSejour::RETABLI, $sejour->getStatut());
        self::assertLessThan($this->horloge->now(), $sejour->getRetabliLe());
        self::assertSame(100, $sejour->getHero()->getPointsDeVie());
        self::assertLessThanOrEqual(20, $sejour->getHero()->getFatigue());

        $this->executer();
        self::assertSame(1, \count(array_filter($this->titresAlertes(), static fn ($t) => 'Récupération terminée' === $t)));
        self::assertSame(1, \count(array_filter($this->titresAlertes(), static fn ($t) => 'Arrivée au QG' === $t)));
        self::assertSame(StatutSejour::RETABLI, $this->sejour()->getStatut(), 'aucune sortie automatique par défaut');
    }

    public function testLeMoteurDeMissionRapatrieEnCoursDeCombat(): void
    {
        $hero = $this->em->find(Hero::class, $this->blesseId)->setPointsDeVie(24)->setVigueur(5)->setCombat(5);
        $mission = $this->em->find(Mission::class, $this->missionId);
        $mission->addVillain($vilain = Builder::villain(10, 95, 90));
        self::persister($this->em, $vilain, $hero, $mission);
        $this->em->clear();

        $this->horloge->sleep(120);
        $this->executer('app:missions:update', ['--sans-infirmerie' => true]);

        $sejour = $this->sejour();
        self::assertNotNull($sejour, 'rapatrié par le moteur, sans passage de l\'infirmerie');
        self::assertSame($this->blesseId, $sejour->getHero()->getId());
        self::assertLessThanOrEqual(20, $sejour->getSanteAdmission());
        $journal = implode("\n", array_column($this->em->getRepository(MissionSimulationState::class)->findOneBy([])->getJournal(), 'message'));
        self::assertStringContainsString('rapatrié au QG', $journal);
        self::assertStringContainsString('Compétences obligatoires désormais manquantes', $journal);
    }

    public function testFinDeMissionAdmetLesHerosEpuises(): void
    {
        $mission = $this->em->find(Mission::class, $this->missionId);
        foreach ($mission->getObjectifs() as $objectif) {
            $objectif->setTermine(true);
        }
        $this->em->find(Hero::class, $this->blesseId)->setPointsDeVie(100);
        $this->em->find(Hero::class, $this->coequipierId)->setFatigue(80);
        $this->em->flush();
        $this->em->clear();

        $this->horloge->sleep(10);
        $this->executer('app:missions:update', ['--sans-infirmerie' => true]);

        self::assertSame(MissionStatut::TERMINEE, $this->em->find(Mission::class, $this->missionId)->getStatut());
        $sejour = $this->sejour();
        self::assertSame($this->coequipierId, $sejour->getHero()->getId(), 'fatigue 80 + 15 en fin de mission');
        self::assertSame(TypeAdmission::POST_MISSION, $sejour->getTypeAdmission());
        self::assertSame(MotifInfirmerie::EPUISEMENT, $sejour->getMotif());
        self::assertSame(HeroStatut::REPOS, $sejour->getHero()->getStatut());
    }

    public function testVerrousContreLesExecutionsConcurrentes(): void
    {
        $verrous = static::getContainer()->get(LockFactory::class);
        $global = $verrous->createLock(InfirmaryUpdateRunner::VERROU, 60);
        self::assertTrue($global->acquire(false));
        try {
            self::assertStringContainsString('déjà en cours', $this->executer());
            self::assertNull($this->sejour());
        } finally {
            $global->release();
        }

        // Mission en cours de simulation par un autre processus : le héros engagé est ignoré.
        $mission = $verrous->createLock('mission-simulation-'.$this->missionId, MissionUpdateRunner::TTL_VERROU);
        self::assertTrue($mission->acquire(false));
        try {
            self::assertStringContainsString('1 héros ignoré(s)', $this->executer());
            self::assertNull($this->sejour());
        } finally {
            $mission->release();
        }
        self::assertStringContainsString('1 admission(s)', $this->executer());
    }
}
