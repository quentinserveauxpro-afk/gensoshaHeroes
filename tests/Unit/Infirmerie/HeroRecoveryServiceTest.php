<?php

namespace App\Tests\Unit\Infirmerie;

use App\Entity\Alerte;
use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Enum\HeroStatut;
use App\Enum\MotifInfirmerie;
use App\Enum\StatutSejour;
use App\Enum\TypeAdmission;
use App\GameTime\GameTimeConfiguration;
use App\GameTime\InfirmaryRecoveryTimeCalculator;
use App\Geo\Coordonnees;
use App\Geo\DistanceCalculator;
use App\Geo\EstimatedArrivalCalculator;
use App\Infirmerie\ContexteRecuperation;
use App\Infirmerie\HeroMedicalEvaluationService;
use App\Infirmerie\HeroRecoveryService;
use App\Infirmerie\InfirmerieAlerteService;
use App\Infirmerie\InfirmerieConfiguration;
use App\Infirmerie\InfirmerieException;
use App\Infirmerie\QgConfiguration;
use App\Repository\AlerteRepository;
use App\Service\Mission\EffectiveStatsResolver;
use App\Tests\Support\Builder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Récupération progressive sur une durée fixée à l'arrivée (horloge explicite : aucune attente).
 * Héros de référence : 40/100 PV, fatigue 50, vigueur 50 → soin de 530 s (cibles 100 PV, fatigue 20).
 */
final class HeroRecoveryServiceTest extends TestCase
{
    private const T0 = '2026-11-20 10:00:00';
    private const DUREE = 530;

    /** @var list<object> */
    private array $persistes = [];

    private function service(?InfirmerieConfiguration $config = null): HeroRecoveryService
    {
        $config ??= new InfirmerieConfiguration();
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $o): void { $this->persistes[] = $o; });

        return new HeroRecoveryService($em, $config, new HeroMedicalEvaluationService($config), new QgConfiguration(),
            new DistanceCalculator(), new EstimatedArrivalCalculator(),
            new InfirmaryRecoveryTimeCalculator(new GameTimeConfiguration(), $config, new EffectiveStatsResolver()),
            new InfirmerieAlerteService($em, $this->createStub(AlerteRepository::class)));
    }

    private function hero(int $pv = 40, int $fatigue = 50, int $pvMax = 100): Hero
    {
        return Builder::hero(['pv' => $pv, 'pvMax' => $pvMax, 'fatigue' => $fatigue, 'vigueur' => 50, 'statut' => HeroStatut::BLESSE]);
    }

    private function enTraitement(HeroRecoveryService $service, Hero $hero, int $fatigueCible = 20): SejourInfirmerie
    {
        $sejour = new SejourInfirmerie($hero, MotifInfirmerie::DECISION_MEDICALE, TypeAdmission::MANUELLE, $hero->getPointsDeVieMaximum(), $fatigueCible, self::a('+0 sec'));
        $service->arriver($sejour, self::a('+0 sec'), new ContexteRecuperation());

        return $sejour;
    }

    private static function a(string $modif): \DateTimeImmutable
    {
        return (new \DateTimeImmutable(self::T0))->modify($modif);
    }

    public function testDureeFixeeALArrivee(): void
    {
        $service = $this->service();
        $sejour = $this->enTraitement($service, $this->hero());
        self::assertSame(StatutSejour::EN_TRAITEMENT, $sejour->getStatut());
        self::assertEquals(self::a('+'.self::DUREE.' seconds'), $sejour->getSortiePrevueLe());
        self::assertSame(self::DUREE, $service->dureeTraitement($sejour));
        self::assertSame(self::DUREE, $service->secondesRestantes($sejour, self::a('+0 sec')));
    }

    public function testProgressionDeLaSanteEtDiminutionDeLaFatigue(): void
    {
        $service = $this->service();
        $hero = $this->hero();
        $sejour = $this->enTraitement($service, $hero);

        $service->actualiser($sejour, self::a('+265 seconds'), new ContexteRecuperation());
        // p = 265 / 530 = 0,5 → santé 40 + 0,5 × 60 = 70 ; fatigue 50 − 0,5 × 30 = 35.
        self::assertEqualsWithDelta(70.0, $sejour->getSanteActuelle(), 1e-9);
        self::assertEqualsWithDelta(35.0, $sejour->getFatigueActuelle(), 1e-9);
        self::assertSame(70, $hero->getPointsDeVie());
        self::assertSame(35, $hero->getFatigue());
        self::assertSame(265, $service->secondesRestantes($sejour, self::a('+265 seconds')));
        self::assertEqualsWithDelta(0.5, $service->etatA($sejour, self::a('+265 seconds'))[2], 1e-9);
    }

    public function testRepriseApresRedemarrageIdentiqueAuxPassagesFrequents(): void
    {
        $service = $this->service();
        $unique = $this->enTraitement($service, $this->hero());
        $service->actualiser($unique, self::a('+400 seconds'), new ContexteRecuperation());

        $frequent = $this->enTraitement($service, $this->hero());
        for ($s = 3; $s <= 400; $s += 3) {
            $service->actualiser($frequent, self::a(sprintf('+%d seconds', $s)), new ContexteRecuperation());
        }
        $service->actualiser($frequent, self::a('+400 seconds'), new ContexteRecuperation());

        self::assertEqualsWithDelta($unique->getSanteActuelle(), $frequent->getSanteActuelle(), 1e-6);
        self::assertEqualsWithDelta($unique->getFatigueActuelle(), $frequent->getFatigueActuelle(), 1e-6);

        // Même instant (y compris microsecondes différentes) : aucun double comptage.
        $avant = $unique->getSanteActuelle();
        $service->actualiser($unique, self::a('+400 seconds')->modify('+900000 microseconds'), new ContexteRecuperation());
        self::assertSame($avant, $unique->getSanteActuelle());
    }

    public function testExpirationAppliqueExactementLesCibles(): void
    {
        $service = $this->service();
        $hero = $this->hero();
        $sejour = $this->enTraitement($service, $hero);

        // Worker arrêté bien au-delà de la fin : rétablissement daté exactement à la fin prévue.
        self::assertSame(['retabli'], $service->actualiser($sejour, self::a('+2 hours'), new ContexteRecuperation()));
        self::assertSame(StatutSejour::RETABLI, $sejour->getStatut());
        self::assertEquals(self::a('+'.self::DUREE.' seconds'), $sejour->getRetabliLe());
        self::assertSame(100.0, $sejour->getSanteActuelle());
        self::assertSame(20.0, $sejour->getFatigueActuelle());
        self::assertSame(100, $hero->getPointsDeVie());
        self::assertSame(20, $hero->getFatigue());
        self::assertSame(0, $service->secondesRestantes($sejour, self::a('+2 hours')), 'jamais négatif');
        self::assertSame(HeroStatut::BLESSE, $hero->getStatut(), 'sortie à valider');

        // Passage suivant : ni transition ni alerte en double.
        self::assertSame([], $service->actualiser($sejour, self::a('+3 hours'), new ContexteRecuperation()));
        self::assertCount(1, array_filter($this->persistes, static fn ($o) => $o instanceof Alerte && 'Récupération terminée' === $o->getTitre()));
    }

    public function testLaSanteNeBaisseJamaisEtLaFatigueNAugmenteJamais(): void
    {
        $service = $this->service();
        $hero = $this->hero(60, 5);
        $sejour = $this->enTraitement($service, $hero);
        $precedente = [$sejour->getSanteActuelle(), $sejour->getFatigueActuelle()];
        for ($s = 10; $s <= 1000; $s += 37) {
            $service->actualiser($sejour, self::a(sprintf('+%d seconds', $s)), new ContexteRecuperation());
            self::assertGreaterThanOrEqual($precedente[0], $sejour->getSanteActuelle());
            self::assertLessThanOrEqual($precedente[1], $sejour->getFatigueActuelle());
            self::assertGreaterThanOrEqual(0.0, $sejour->getSanteActuelle());
            self::assertLessThanOrEqual(100.0, $sejour->getSanteActuelle());
            $precedente = [$sejour->getSanteActuelle(), $sejour->getFatigueActuelle()];
        }
        self::assertSame(5, $hero->getFatigue(), 'fatigue sous la cible : jamais augmentée');
        self::assertSame(100, $hero->getPointsDeVie());
    }

    public function testSortieManuelleEtRefusAvantRetablissement(): void
    {
        $service = $this->service();
        $hero = $this->hero();
        $sejour = $this->enTraitement($service, $hero);
        try {
            $service->sortir($sejour, self::a('+1 minute'));
            self::fail('Sortie acceptée avant rétablissement.');
        } catch (InfirmerieException) {
        }
        $service->actualiser($sejour, self::a('+10 minutes'), new ContexteRecuperation());
        $service->sortir($sejour, self::a('+11 minutes'));
        self::assertSame(StatutSejour::SORTI, $sejour->getStatut());
        self::assertNull($sejour->getCleActive());
        self::assertSame(HeroStatut::DISPONIBLE, $hero->getStatut());
    }

    public function testSortieAutomatique(): void
    {
        $service = $this->service(new InfirmerieConfiguration(sortieAutomatique: true));
        $hero = $this->hero();
        $sejour = $this->enTraitement($service, $hero);
        self::assertSame(['retabli', 'sorti'], $service->actualiser($sejour, self::a('+15 minutes'), new ContexteRecuperation()));
        self::assertEquals(self::a('+'.self::DUREE.' seconds'), $sejour->getSortiLe());
        self::assertSame(HeroStatut::DISPONIBLE, $hero->getStatut());
    }

    public function testRetourAuQgPuisSoinApresInterruption(): void
    {
        $service = $this->service();
        $hero = $this->hero();
        $sejour = new SejourInfirmerie($hero, MotifInfirmerie::DECISION_MEDICALE, TypeAdmission::MANUELLE, 100, 20, self::a('+0 sec'));
        $sejour->commencerRetour(new Coordonnees(45.7640, 4.8357), 392.0, 3528.0, self::a('+0 sec'), self::a('+400 seconds'));

        self::assertSame([], $service->actualiser($sejour, self::a('+200 seconds'), new ContexteRecuperation()));
        self::assertSame(StatutSejour::RETOUR_QG, $sejour->getStatut());
        self::assertEqualsWithDelta(40.0, $sejour->getSanteActuelle(), 1e-9, 'aucun soin pendant le retour');
        self::assertEqualsWithDelta(0.5, $service->progressionRetour($sejour, self::a('+200 seconds')), 1e-9);
        self::assertGreaterThan(45.7640, $service->positionRetour($sejour, self::a('+200 seconds'))->latitude);

        // Arrêt du worker : arrivée à l'heure prévue, soin compté depuis l'arrivée.
        self::assertSame(['arrivee'], $service->actualiser($sejour, self::a('+665 seconds'), new ContexteRecuperation()));
        self::assertEquals(self::a('+400 seconds'), $sejour->getDebutTraitementLe());
        self::assertEquals(self::a('+930 seconds'), $sejour->getSortiePrevueLe());
        self::assertEqualsWithDelta(70.0, $sejour->getSanteActuelle(), 1e-9);
        self::assertTrue((new QgConfiguration())->coordonnees()->egale($hero->getCoordonnees()));
    }
}
