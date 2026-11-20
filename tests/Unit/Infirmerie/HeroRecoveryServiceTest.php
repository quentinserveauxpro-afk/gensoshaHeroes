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
 * Récupération progressive (horloge explicite : aucune attente). Règle de sortie : points de vie
 * au maximum ET fatigue à 0.
 * Héros de référence : 40/100 PV, fatigue 50, vigueur 50 → soin de 530 s ; santé en 530 s
 * (poids 0,36), fatigue en 294 s (poids 0,20) : la sortie suit la santé, la plus longue.
 */
final class HeroRecoveryServiceTest extends TestCase
{
    private const T0 = '2026-11-20 10:00:00';
    private const DUREE = 530;
    private const DUREE_FATIGUE = 294;

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
        // Santé : 40 + 265/530 × 60 = 70 ; fatigue : 50 − 265/294 × 50 ≈ 4,93.
        self::assertEqualsWithDelta(70.0, $sejour->getSanteActuelle(), 1e-6);
        self::assertEqualsWithDelta(50 - 265 / self::DUREE_FATIGUE * 50, $sejour->getFatigueActuelle(), 1e-6);
        self::assertSame(70, $hero->getPointsDeVie());
        self::assertSame(5, $hero->getFatigue());
        self::assertSame(265, $service->secondesRestantes($sejour, self::a('+265 seconds')));
        self::assertEqualsWithDelta(0.5, $service->etatA($sejour, self::a('+265 seconds'))[2], 1e-9);
    }

    public function testFatigueNulleAvantLaSanteLaSanteContinue(): void
    {
        $service = $this->service();
        $hero = $this->hero();
        $sejour = $this->enTraitement($service, $hero);

        // Fatigue nulle à 294 s : elle s'arrête, la santé continue jusqu'à 530 s.
        $service->actualiser($sejour, self::a('+300 seconds'), new ContexteRecuperation());
        self::assertSame(0.0, $sejour->getFatigueActuelle());
        self::assertSame(0, $hero->getFatigue());
        self::assertLessThan(100, $hero->getPointsDeVie());
        self::assertSame(StatutSejour::EN_TRAITEMENT, $sejour->getStatut(), 'santé 99 % + fatigue 0 : reste en infirmerie');
        // Temps restant calculé sur la santé.
        self::assertSame(['sante' => 230, 'fatigue' => 0, 'total' => 230], $service->restants($sejour, self::a('+300 seconds')));

        $service->actualiser($sejour, self::a('+529 seconds'), new ContexteRecuperation());
        self::assertSame(99, $hero->getPointsDeVie());
        self::assertSame(StatutSejour::EN_TRAITEMENT, $sejour->getStatut());
        self::assertSame(['retabli'], $service->actualiser($sejour, self::a('+530 seconds'), new ContexteRecuperation()));
    }

    public function testSanteMaximaleAvantLaFatigueLaFatigueContinue(): void
    {
        $service = $this->service();
        $hero = $this->hero(90, 80);
        $sejour = $this->enTraitement($service, $hero);
        $restants = $service->restants($sejour, self::a('+0 sec'));
        // Temps restant calculé sur la fatigue : la plus longue des deux.
        self::assertGreaterThan($restants['sante'], $restants['fatigue']);
        self::assertSame($restants['fatigue'], $restants['total']);

        $apresSante = self::a(sprintf('+%d seconds', $restants['sante']));
        $service->actualiser($sejour, $apresSante, new ContexteRecuperation());
        self::assertSame(100, $hero->getPointsDeVie(), 'santé maximale : la récupération de santé s\'arrête');
        self::assertGreaterThan(0, $hero->getFatigue(), 'la fatigue continue de baisser');
        self::assertSame(StatutSejour::EN_TRAITEMENT, $sejour->getStatut(), 'santé 100 % + fatigue > 0 : reste en infirmerie');
        self::assertSame(0, $service->restants($sejour, $apresSante)['sante']);

        $fatigue = $hero->getFatigue();
        $service->actualiser($sejour, $apresSante->modify('+10 seconds'), new ContexteRecuperation());
        self::assertSame(100, $hero->getPointsDeVie());
        self::assertLessThan($fatigue, $hero->getFatigue());

        $service->actualiser($sejour, self::a(sprintf('+%d seconds', $restants['total'] - 1)), new ContexteRecuperation());
        self::assertSame(1, $hero->getFatigue(), 'santé 100 % + fatigue 1 : reste en infirmerie');
        self::assertSame(StatutSejour::EN_TRAITEMENT, $sejour->getStatut());
        self::assertSame(['retabli'], $service->actualiser($sejour, self::a(sprintf('+%d seconds', $restants['total'])), new ContexteRecuperation()));
        self::assertSame(0, $hero->getFatigue());
    }

    public function testTempsRestantSurLesDeuxComposantes(): void
    {
        $service = $this->service();
        $sejour = $this->enTraitement($service, $this->hero());
        self::assertSame(['sante' => self::DUREE, 'fatigue' => self::DUREE_FATIGUE, 'total' => self::DUREE], $service->restants($sejour, self::a('+0 sec')));
        self::assertSame(['sante' => 430, 'fatigue' => 194, 'total' => 430], $service->restants($sejour, self::a('+100 seconds')));
    }

    public function testSanteMaximaleEtFatigue20RestentEnInfirmerie(): void
    {
        $service = $this->service();
        $hero = $this->hero(100, 20);
        $sejour = $this->enTraitement($service, $hero);
        self::assertSame(0, $service->restants($sejour, self::a('+0 sec'))['sante']);
        self::assertGreaterThan(0, $service->secondesRestantes($sejour, self::a('+0 sec')));
        try {
            $service->sortir($sejour, self::a('+1 second'));
            self::fail('Sortie acceptée avec une fatigue de 20.');
        } catch (InfirmerieException $e) {
            self::assertStringContainsString('fatigue 20', $e->getMessage());
        }
        $service->actualiser($sejour, self::a('+15 minutes'), new ContexteRecuperation());
        self::assertSame(StatutSejour::RETABLI, $sejour->getStatut());
        self::assertSame([100, 0], [$hero->getPointsDeVie(), $hero->getFatigue()]);
    }

    public function testAncienSejourRetabliAvecFatigueReprendSaRecuperation(): void
    {
        $service = $this->service(new InfirmerieConfiguration(sortieAutomatique: true));
        $hero = $this->hero(100, 20);
        // Déclaré rétabli selon l'ancienne règle (fatigue ≤ 20) : interdit désormais.
        $sejour = new SejourInfirmerie($hero, MotifInfirmerie::DECISION_MEDICALE, TypeAdmission::MANUELLE, 100, 20, self::a('-1 hour'));
        $sejour->arriverAuQg(self::a('-1 hour'))->marquerRetabli(self::a('-30 minutes'));

        self::assertFalse($service->sortieAutorisee($sejour));
        self::assertSame([], $service->actualiser($sejour, self::a('+0 sec'), new ContexteRecuperation()), 'pas de sortie automatique');
        self::assertSame(StatutSejour::RETABLI, $sejour->getStatut());
        self::assertGreaterThan(self::a('+0 sec'), $sejour->getSortiePrevueLe(), 'reprise planifiée');

        $service->actualiser($sejour, self::a('+15 minutes'), new ContexteRecuperation());
        self::assertSame(StatutSejour::SORTI, $sejour->getStatut());
        self::assertSame(0, $hero->getFatigue());
        self::assertSame(HeroStatut::DISPONIBLE, $hero->getStatut());
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
        self::assertSame(0.0, $sejour->getFatigueActuelle());
        self::assertSame(100, $hero->getPointsDeVie());
        self::assertSame(0, $hero->getFatigue());
        self::assertSame(0, $service->secondesRestantes($sejour, self::a('+2 hours')), 'jamais négatif');
        self::assertSame(HeroStatut::BLESSE, $hero->getStatut(), 'sortie à valider');
        self::assertTrue($service->sortieAutorisee($sejour));

        // Passage suivant : ni transition ni alerte en double.
        self::assertSame([], $service->actualiser($sejour, self::a('+3 hours'), new ContexteRecuperation()));
        $alertes = array_values(array_filter($this->persistes, static fn ($o) => $o instanceof Alerte && 'Récupération terminée' === $o->getTitre()));
        self::assertCount(1, $alertes);
        self::assertStringContainsString('100 / 100 PV, fatigue 0', $alertes[0]->getMessage());
    }

    public function testAucuneNotificationDeRetablissementAvantFatigueNulle(): void
    {
        $service = $this->service();
        $sejour = $this->enTraitement($service, $this->hero(90, 80));
        $sante = $service->restants($sejour, self::a('+0 sec'))['sante'];
        $service->actualiser($sejour, self::a(sprintf('+%d seconds', $sante + 5)), new ContexteRecuperation());
        $retablis = static fn (array $persistes) => \count(array_filter($persistes, static fn ($o) => $o instanceof Alerte && 'Récupération terminée' === $o->getTitre()));
        self::assertSame(0, $retablis($this->persistes), 'santé maximale seule : aucune notification');
        $service->actualiser($sejour, self::a('+15 minutes'), new ContexteRecuperation());
        self::assertSame(1, $retablis($this->persistes));
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
            self::assertGreaterThanOrEqual(0.0, $sejour->getFatigueActuelle());
            self::assertLessThanOrEqual(100.0, $sejour->getSanteActuelle());
            $precedente = [$sejour->getSanteActuelle(), $sejour->getFatigueActuelle()];
        }
        self::assertSame(0, $hero->getFatigue());
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
