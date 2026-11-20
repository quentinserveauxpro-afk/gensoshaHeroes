<?php

namespace App\Tests\Integration;

use App\Entity\AffectationMission;
use App\Entity\Alerte;
use App\Entity\GeoPosition;
use App\Entity\HeroMovement;
use App\Entity\Mission;
use App\Entity\MissionSimulationState;
use App\Entity\ObjectifMission;
use App\Enum\AffectationStatut;
use App\Enum\MissionStatut;
use App\Enum\MouvementStatut;
use App\Simulation\MissionUpdateRunner;
use App\Tests\Support\Builder;
use App\Tests\Support\ResetDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;

/** Moteur temps réel : déplacements, arrivée, simulation, idempotence et verrous. */
final class MissionsUpdateCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use ResetDatabaseTrait;

    private EntityManagerInterface $em;
    private int $missionId;
    private int $heroId;
    private \Symfony\Component\Clock\MockClock $horloge;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->horloge = self::mockTime('2026-11-20 10:00:00');
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::viderBase($this->em);

        $now = new \DateTimeImmutable('2026-11-20 10:00:00');
        $mission = Builder::mission(['statut' => MissionStatut::EN_COURS, 'fin' => $now->modify('+3 hours'), 'urgence' => \App\Enum\NiveauUrgence::MOYENNE]);
        $mission->setGeoPosition(GeoPosition::en(45.7640, 4.8357))->setDateDebut($now);
        $mission->addObjectif((new ObjectifMission())->setTitre('Sécuriser la zone')->setObligatoire(true)->setOrdreAffichage(1));
        // ≈ 11 km au nord du point de mission.
        $vilain = Builder::villain(8, 85, 70);
        $mission->addVillain($vilain);
        $hero = Builder::hero(['pv' => 600, 'pvMax' => 600, 'vigueur' => 60, 'combat' => 90, 'mobilite' => 100]);
        $hero->setGeoPosition(GeoPosition::en(45.8640, 4.8357));
        self::persister($this->em, $vilain, $mission, $hero);
        self::persister($this->em, new AffectationMission($hero, $mission, $now));
        $this->missionId = (int) $mission->getId();
        $this->heroId = (int) $hero->getId();
        $this->em->clear();
    }

    private function executer(): string
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:missions:update'));
        self::assertSame(0, $tester->execute([]));
        $this->em->clear();

        return $tester->getDisplay();
    }

    private function mouvement(): HeroMovement
    {
        return $this->em->getRepository(HeroMovement::class)->findOneBy([]);
    }

    public function testCycleCompletDeplacementArriveeSimulationFin(): void
    {
        // 1. Premier passage : départ du héros (déplacement manquant créé).
        self::assertStringContainsString('1 créé(s)', $this->executer());
        $m = $this->mouvement();
        self::assertSame(MouvementStatut::EN_DEPLACEMENT, $m->getStatut());
        self::assertEqualsWithDelta(11.1, $m->getDistanceKm(), 0.2);

        // 2. Relance immédiate : idempotente.
        self::assertStringContainsString('0 créé(s)', $this->executer());
        self::assertCount(1, $this->em->getRepository(HeroMovement::class)->findAll());

        // 3. À mi-parcours, le héros a avancé mais n'est pas arrivé.
        $duree = $m->getDateArriveePrevue()->getTimestamp() - $m->getDateDepart()->getTimestamp();
        $this->horloge->sleep(intdiv($duree, 2));
        $this->executer();
        $m = $this->mouvement();
        self::assertSame(MouvementStatut::EN_DEPLACEMENT, $m->getStatut());
        self::assertEqualsWithDelta(50, $m->getProgression(), 2);

        // 4. Après l'heure prévue : arrivée actée par le serveur, engagement.
        $this->horloge->sleep(intdiv($duree, 2) + 2);
        self::assertStringContainsString('1 arrivée(s)', $this->executer());
        $m = $this->mouvement();
        self::assertSame(MouvementStatut::ARRIVE, $m->getStatut());
        self::assertNotNull($m->getDateArriveeReelle());
        $affectation = $this->em->getRepository(AffectationMission::class)->findOneBy([]);
        self::assertSame(AffectationStatut::SUR_PLACE, $affectation->getStatut());
        $etat = $this->em->getRepository(MissionSimulationState::class)->findOneBy([]);
        self::assertNotNull($etat->getDateEngagement());

        // 5. Une minute d'engagement = 12 étapes de 5 s ; un second passage n'en refait aucune.
        $this->horloge->sleep(60);
        self::assertStringContainsString('12 étape(s)', $this->executer());
        self::assertStringContainsString('0 étape(s)', $this->executer());
        $objectif = $this->em->getRepository(ObjectifMission::class)->findOneBy([]);
        self::assertGreaterThan(0, $objectif->getProgression());
        $hero = $this->em->find(\App\Entity\Hero::class, $this->heroId);
        self::assertLessThan(600, $hero->getPointsDeVie(), 'Le combat inflige des dégâts.');

        // 6. Suffisamment de temps : objectifs obligatoires atteints, mission réussie.
        $this->horloge->sleep(3000);
        $this->executer();
        $mission = $this->em->find(Mission::class, $this->missionId);
        self::assertSame(MissionStatut::TERMINEE, $mission->getStatut());
        self::assertSame(100, $mission->getTauxDeReussite());
        self::assertTrue($this->em->getRepository(ObjectifMission::class)->findOneBy([])->isTermine());
        self::assertSame('SUCCES', $this->em->getRepository(MissionSimulationState::class)->findOneBy([])->getIssue());
        self::assertGreaterThan(0, $this->em->getRepository(Alerte::class)->count([]));
    }

    public function testMissionVerrouilleeNEstPasTraiteeDeuxFois(): void
    {
        $verrou = static::getContainer()->get(LockFactory::class)->createLock('mission-simulation-'.$this->missionId, MissionUpdateRunner::TTL_VERROU);
        self::assertTrue($verrou->acquire(false));
        try {
            self::assertStringContainsString('1 ignorée(s) (verrou)', $this->executer());
            self::assertCount(0, $this->em->getRepository(HeroMovement::class)->findAll());
        } finally {
            $verrou->release();
        }
        self::assertStringContainsString('1 créé(s)', $this->executer());
    }

    public function testEcheanceDepasseeAvantArriveeFaitEchouerLaMission(): void
    {
        // Héros à La Réunion : près de 9 000 km, trajet plafonné à 10 min (échelle accélérée),
        // échéance de la mission dans 5 min : impossible d'arriver à temps.
        $hero = $this->em->find(\App\Entity\Hero::class, $this->heroId);
        $hero->getGeoPosition()->setCoordonnees(new \App\Geo\Coordonnees(-20.8823, 55.4504));
        $this->em->find(Mission::class, $this->missionId)->setDateFinPrevue(new \DateTimeImmutable('2026-11-20 10:05:00'));
        $this->em->flush();
        $this->em->clear();

        $this->executer();
        self::assertGreaterThan(8500, $this->mouvement()->getDistanceKm());
        $m = $this->mouvement();
        $duree = $m->getDateArriveePrevue()->getTimestamp() - $m->getDateDepart()->getTimestamp();
        self::assertLessThanOrEqual(600, $duree, 'plafond de 10 min');
        self::assertGreaterThan(300, $duree, 'arrivée après l\'échéance');
        $this->horloge->sleep(6 * 60);
        $this->executer();
        self::assertSame(MissionStatut::ECHOUEE, $this->em->find(Mission::class, $this->missionId)->getStatut());
        self::assertSame(MouvementStatut::INTERROMPU, $this->mouvement()->getStatut());
    }
}
