<?php

namespace App\Tests\Integration;

use App\Entity\AffectationMission;
use App\Entity\GeoPosition;
use App\Entity\Hero;
use App\Entity\HeroMovement;
use App\Entity\SejourInfirmerie;
use App\Enum\AffectationStatut;
use App\Enum\MissionStatut;
use App\Enum\MotifInfirmerie;
use App\Enum\MouvementStatut;
use App\Enum\StatutSejour;
use App\Enum\TypeAdmission;
use App\Geo\Coordonnees;
use App\Infirmerie\HeroRepatriationService;
use App\Tests\Support\Builder;
use App\Tests\Support\ResetDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

/** Normalisation des trajets et soins créés avec l'ancienne échelle (heures) : jamais rallongés. */
final class GameTimeNormalizeCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use ResetDatabaseTrait;

    private EntityManagerInterface $em;
    private MockClock $horloge;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->horloge = self::mockTime('2026-11-20 10:00:00');
        $this->now = new \DateTimeImmutable('2026-11-20 10:00:00');
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::viderBase($this->em);
    }

    private function executer(array $options = []): string
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:game-time:normalize'));
        self::assertSame(0, $tester->execute($options));
        $this->em->clear();

        return $tester->getDisplay();
    }

    /** Ancien trajet Paris → Lyon de 2 h, à mi-parcours. */
    private function trajet(string $depart = '-1 hour', string $arrivee = '+1 hour'): HeroMovement
    {
        $mission = Builder::mission(['statut' => MissionStatut::EN_COURS, 'fin' => $this->now->modify('+3 hours')]);
        $mission->setGeoPosition(GeoPosition::en(45.764, 4.8357));
        $hero = Builder::hero(['mobilite' => 50, 'statut' => \App\Enum\HeroStatut::EN_MISSION]);
        $affectation = (new AffectationMission($hero, $mission, $this->now->modify($depart)))->setStatut(AffectationStatut::AFFECTE);
        $paris = new Coordonnees(48.8566, 2.3522);
        $m = new HeroMovement($hero, $mission, $paris, new Coordonnees(45.764, 4.8357), 392.0, 196.0,
            $this->now->modify($depart), $this->now->modify($arrivee), $affectation);
        $m->avancer($paris, 0.0, $this->now->modify($depart));
        self::persister($this->em, $mission, $hero, $affectation, $m);

        return $m;
    }

    /** Ancien soin de 8 h, 50 % de la santé déjà récupérée. */
    private function soin(string $fin = '+8 hours'): SejourInfirmerie
    {
        $hero = Builder::hero(['pv' => 40, 'pvMax' => 100, 'fatigue' => 20, 'vigueur' => 50]);
        self::persister($this->em, $hero); // le séjour actif est indexé par l'identifiant du héros
        $sejour = new SejourInfirmerie($hero, MotifInfirmerie::BLESSURE, TypeAdmission::MANUELLE, 100, 20, $this->now->modify('-8 hours'));
        $sejour->arriverAuQg($this->now->modify('-8 hours'));
        $sejour->enregistrerRecuperation(70.0, 20.0, $this->now->modify('-10 seconds'), $this->now->modify($fin));
        self::persister($this->em, $sejour);

        return $sejour;
    }

    public function testDryRunNeModifieRien(): void
    {
        $m = $this->trajet();
        $s = $this->soin();
        $sortie = $this->executer(['--dry-run' => true]);

        self::assertStringContainsString('Simulation : 2 opération(s) seraient modifiées', $sortie);
        self::assertStringContainsString('raccourci', $sortie);
        self::assertEquals($this->now->modify('+1 hour'), $this->em->find(HeroMovement::class, $m->getId())->getDateArriveePrevue());
        self::assertEquals($this->now->modify('+8 hours'), $this->em->find(SejourInfirmerie::class, $s->getId())->getSortiePrevueLe());
        self::assertEqualsWithDelta(70.0, $this->em->find(SejourInfirmerie::class, $s->getId())->getSanteActuelle(), 1e-9);
    }

    public function testTrajetRaccourciEnConservantLaProgression(): void
    {
        $id = $this->trajet()->getId();
        $this->executer();
        $m = $this->em->find(HeroMovement::class, $id);
        $duree = $m->getDateArriveePrevue()->getTimestamp() - $m->getDateDepart()->getTimestamp();

        // Paris → Lyon, mobilité 50 : 30 + 0,392 × 570 = 253 s ; mi-parcours conservé.
        self::assertEqualsWithDelta(253, $duree, 1);
        self::assertLessThanOrEqual(600, $duree);
        self::assertEqualsWithDelta(0.5, ($this->now->getTimestamp() - $m->getDateDepart()->getTimestamp()) / $duree, 0.01);
        self::assertSame(MouvementStatut::EN_DEPLACEMENT, $m->getStatut());
        self::assertEquals(392.0, $m->getDistanceKm(), 'distance réelle inchangée');

        // Relance : rien à faire (idempotent, aucun double traitement ni rallongement).
        self::assertStringContainsString('0 opération(s) normalisée(s) sur 1', $this->executer());
        self::assertEquals($m->getDateArriveePrevue(), $this->em->find(HeroMovement::class, $id)->getDateArriveePrevue());
    }

    public function testJamaisRallonge(): void
    {
        $id = $this->trajet('-10 seconds', '+20 seconds')->getId();
        $sortie = $this->executer();
        self::assertStringContainsString('inchangé', $sortie);
        self::assertEquals($this->now->modify('+20 seconds'), $this->em->find(HeroMovement::class, $id)->getDateArriveePrevue());
    }

    public function testEcheancesDepasseesClotureesImmediatement(): void
    {
        $m = $this->trajet('-3 hours', '-1 hour');
        $s = $this->soin('-5 minutes');
        $sortie = $this->executer();
        self::assertStringContainsString('clôturé', $sortie);

        $m = $this->em->find(HeroMovement::class, $m->getId());
        self::assertSame(MouvementStatut::ARRIVE, $m->getStatut());
        self::assertEquals($this->now->modify('-1 hour'), $m->getDateArriveeReelle());
        self::assertSame(AffectationStatut::SUR_PLACE, $this->em->getRepository(AffectationMission::class)->findOneBy([])->getStatut());

        $s = $this->em->find(SejourInfirmerie::class, $s->getId());
        self::assertSame(StatutSejour::RETABLI, $s->getStatut());
        self::assertEquals($this->now->modify('-5 minutes'), $s->getRetabliLe());
        self::assertSame(100, $s->getHero()->getPointsDeVie());
    }

    public function testSoinRaccourciValeursAcquisesConservees(): void
    {
        $id = $this->soin()->getId();
        $this->executer();
        $s = $this->em->find(SejourInfirmerie::class, $id);

        // Gravité (40 PV, fatigue 20) = 0,36 + 0,08 = 0,44 → 429,6 s × 1,1 (blessure) = 473 s ; moitié acquise.
        $restant = $s->getSortiePrevueLe()->getTimestamp() - $this->now->getTimestamp();
        self::assertEqualsWithDelta(237, $restant, 1);
        self::assertLessThanOrEqual(900, $restant);
        self::assertEqualsWithDelta(70.0, $s->getSanteActuelle(), 1e-9, 'progression acquise conservée');
        self::assertSame(StatutSejour::EN_TRAITEMENT, $s->getStatut());

        // Le worker reprend : rétablissement à la nouvelle échéance, jamais au-delà de 15 min.
        $this->horloge->sleep(300);
        $tester = new CommandTester((new Application(self::$kernel))->find('app:infirmary:update'));
        $tester->execute([]);
        $this->em->clear();
        self::assertSame(StatutSejour::RETABLI, $this->em->find(SejourInfirmerie::class, $id)->getStatut());
    }

    public function testRetourAuQgRaccourciEtNouveauRetourPlafonne(): void
    {
        // Rapatriement Lyon → Paris créé avec l'échelle accélérée : au plus 10 min.
        $hero = Builder::hero(['pv' => 10, 'pvMax' => 100]);
        $hero->setGeoPosition(GeoPosition::en(45.764, 4.8357));
        self::persister($this->em, $hero);
        $sejour = static::getContainer()->get(HeroRepatriationService::class)->rapatrierSiNecessaire($hero, $this->now);
        $this->em->flush();
        $duree = $sejour->getArriveeQgPrevueLe()->getTimestamp() - $this->now->getTimestamp();
        self::assertGreaterThanOrEqual(30, $duree);
        self::assertLessThanOrEqual(600, $duree);

        // Ancien retour de 2 h : raccourci.
        $sejour->replanifierRetour($this->now->modify('-1 hour'), $this->now->modify('+1 hour'), 196.0, $this->now);
        $this->em->flush();
        $this->executer();
        $s = $this->em->find(SejourInfirmerie::class, $sejour->getId());
        self::assertLessThanOrEqual(600, $s->getArriveeQgPrevueLe()->getTimestamp() - $s->getDepartRetourLe()->getTimestamp());
        self::assertLessThan($this->now->modify('+10 minutes'), $s->getArriveeQgPrevueLe());
    }

    public function testRienANormaliser(): void
    {
        self::assertStringContainsString('rien à normaliser', $this->executer());
    }
}
