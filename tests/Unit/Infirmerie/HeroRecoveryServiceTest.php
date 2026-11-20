<?php

namespace App\Tests\Unit\Infirmerie;

use App\Entity\Alerte;
use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Enum\HeroStatut;
use App\Enum\MotifInfirmerie;
use App\Enum\StatutSejour;
use App\Enum\TypeAdmission;
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

/** Récupération calculée à partir du temps écoulé (horloge explicite, aucune base de données). */
final class HeroRecoveryServiceTest extends TestCase
{
    private const T0 = '2026-11-20 10:00:00';

    /** @var list<object> */
    private array $persistes = [];

    private function service(?InfirmerieConfiguration $config = null): HeroRecoveryService
    {
        $config ??= new InfirmerieConfiguration();
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $o): void { $this->persistes[] = $o; });
        $alertes = new InfirmerieAlerteService($em, $this->createStub(AlerteRepository::class));

        return new HeroRecoveryService($em, $config, new HeroMedicalEvaluationService($config), new QgConfiguration(),
            new DistanceCalculator(), new EstimatedArrivalCalculator(), new EffectiveStatsResolver(), $alertes);
    }

    /** Héros neutre : vigueur 50 et niveau 0 donnent des modificateurs de 1. */
    private function hero(int $pv = 40, int $fatigue = 50, int $pvMax = 100, array $o = []): Hero
    {
        return Builder::hero($o + ['pv' => $pv, 'pvMax' => $pvMax, 'fatigue' => $fatigue, 'vigueur' => 50, 'niveau' => 0, 'statut' => HeroStatut::BLESSE]);
    }

    private function sejour(Hero $hero, MotifInfirmerie $motif = MotifInfirmerie::DECISION_MEDICALE, string $t = self::T0): SejourInfirmerie
    {
        return new SejourInfirmerie($hero, $motif, TypeAdmission::MANUELLE, $hero->getPointsDeVieMaximum(), 20, new \DateTimeImmutable($t));
    }

    private function enTraitement(Hero $hero, MotifInfirmerie $motif = MotifInfirmerie::DECISION_MEDICALE): SejourInfirmerie
    {
        $sejour = $this->sejour($hero, $motif);
        $sejour->arriverAuQg(new \DateTimeImmutable(self::T0));

        return $sejour;
    }

    private static function a(string $modif): \DateTimeImmutable
    {
        return (new \DateTimeImmutable(self::T0))->modify($modif);
    }

    public function testTauxDeBase(): void
    {
        $taux = $this->service()->taux($this->sejour($this->hero()), new ContexteRecuperation(), self::a('+0 sec'));
        self::assertEqualsWithDelta(2 / 5, $taux->santeParMinute, 1e-9, '2 PV toutes les 5 minutes');
        self::assertEqualsWithDelta(3 / 5, $taux->fatigueParMinute, 1e-9, '3 points de fatigue toutes les 5 minutes');
    }

    public function testBonusDeSoutienMedicalParAptitudeEtNonParNom(): void
    {
        $service = $this->service();
        $hero = $this->hero();
        $sejour = $this->sejour($hero);
        $sans = $service->taux($sejour, new ContexteRecuperation([]), self::a('+0 sec'));
        $avec = $service->taux($sejour, new ContexteRecuperation([4242]), self::a('+0 sec'));
        self::assertEqualsWithDelta($sans->santeParMinute * 1.5, $avec->santeParMinute, 1e-9);
        self::assertEqualsWithDelta($sans->fatigueParMinute * 1.5, $avec->fatigueParMinute, 1e-9);

        // Le patient possédant lui-même l'aptitude : demi-bonus.
        $soigneur = $this->hero(o: ['aptitudes' => [Builder::aptitude('SUPPORT_MEDICAL')]]);
        $propre = $service->taux($this->sejour($soigneur), new ContexteRecuperation([]), self::a('+0 sec'));
        self::assertEqualsWithDelta($sans->santeParMinute * 1.25, $propre->santeParMinute, 1e-9);
    }

    public function testModificateursVigueurNiveauGraviteCapacite(): void
    {
        $service = $this->service();
        $base = $service->taux($this->sejour($this->hero()), new ContexteRecuperation(), self::a('+0 sec'))->santeParMinute;

        $robuste = $service->taux($this->sejour($this->hero(o: ['vigueur' => 100, 'niveau' => 100])), new ContexteRecuperation(), self::a('+0 sec'));
        self::assertEqualsWithDelta($base * 1.25 * 1.5, $robuste->santeParMinute, 1e-9);

        $grave = $service->taux($this->sejour($this->hero(pv: 10)), new ContexteRecuperation(), self::a('+0 sec'));
        self::assertEqualsWithDelta($base * 0.8, $grave->santeParMinute, 1e-9, 'admis en santé critique');
        $ko = $service->taux($this->sejour($this->hero(pv: 0)), new ContexteRecuperation(), self::a('+0 sec'));
        self::assertEqualsWithDelta($base * 0.6, $ko->santeParMinute, 1e-9, 'admis à 0 PV');

        $sature = $service->taux($this->sejour($this->hero()), new ContexteRecuperation([], 12), self::a('+0 sec'));
        self::assertEqualsWithDelta($base * 0.5, $sature->santeParMinute, 1e-9, '12 patients pour 6 lits');
    }

    public function testRecuperationProportionnelleAuTempsEtIdempotente(): void
    {
        $service = $this->service();
        $hero = $this->hero(40, 50);
        $sejour = $this->enTraitement($hero);

        $service->actualiser($sejour, self::a('+25 minutes'), new ContexteRecuperation());
        self::assertEqualsWithDelta(50.0, $sejour->getSanteActuelle(), 1e-6, '+10 PV en 25 min');
        self::assertEqualsWithDelta(35.0, $sejour->getFatigueActuelle(), 1e-6, '−15 fatigue en 25 min');
        self::assertSame(50, $hero->getPointsDeVie());
        self::assertSame(35, $hero->getFatigue());

        // Même instant : aucun double comptage.
        $service->actualiser($sejour, self::a('+25 minutes'), new ContexteRecuperation());
        self::assertEqualsWithDelta(50.0, $sejour->getSanteActuelle(), 1e-6);

        // Passages fréquents ou un seul passage après une interruption : même résultat.
        $frequent = $this->enTraitement($this->hero(40, 50));
        for ($s = 3; $s <= 1500; $s += 3) {
            $service->actualiser($frequent, self::a(sprintf('+%d seconds', $s)), new ContexteRecuperation());
        }
        self::assertEqualsWithDelta($sejour->getSanteActuelle(), $frequent->getSanteActuelle(), 1e-6);
        self::assertEqualsWithDelta($sejour->getFatigueActuelle(), $frequent->getFatigueActuelle(), 1e-6);
        self::assertNotNull($sejour->getSortiePrevueLe(), 'temps de récupération estimé');
    }

    public function testMicrosecondesNonComptéesDeuxFois(): void
    {
        $service = $this->service();
        $sejour = $this->enTraitement($this->hero(40, 50));
        $service->actualiser($sejour, self::a('+60 seconds')->modify('+900000 microseconds'), new ContexteRecuperation());
        $service->actualiser($sejour, self::a('+60 seconds')->modify('+950000 microseconds'), new ContexteRecuperation());
        self::assertEqualsWithDelta(40.4, $sejour->getSanteActuelle(), 1e-6);
    }

    public function testBornesJamaisDepassees(): void
    {
        $service = $this->service(new InfirmerieConfiguration(santeSortie: 100.0, fatigueSortie: 0.0, sortieAutomatique: false));
        $hero = $this->hero(95, 5);
        $sejour = new SejourInfirmerie($hero, MotifInfirmerie::BLESSURE, TypeAdmission::MANUELLE, 100, 0, new \DateTimeImmutable(self::T0));
        $sejour->arriverAuQg(new \DateTimeImmutable(self::T0));
        $service->actualiser($sejour, self::a('+10 days'), new ContexteRecuperation());
        self::assertSame(100.0, $sejour->getSanteActuelle());
        self::assertSame(0.0, $sejour->getFatigueActuelle());
        self::assertSame(100, $hero->getPointsDeVie());
        self::assertSame(0, $hero->getFatigue());
    }

    public function testRetablissementDateExacteEtValidationManuelle(): void
    {
        $service = $this->service();
        $hero = $this->hero(90, 20);
        $sejour = $this->enTraitement($hero);

        // 10 PV à 0,4 PV/min = 25 minutes ; la fatigue est déjà au seuil de sortie.
        $evenements = $service->actualiser($sejour, self::a('+2 hours'), new ContexteRecuperation());
        self::assertSame(['retabli'], $evenements);
        self::assertSame(StatutSejour::RETABLI, $sejour->getStatut());
        self::assertEquals(self::a('+25 minutes'), $sejour->getRetabliLe());
        self::assertSame(100, $hero->getPointsDeVie());
        self::assertSame(HeroStatut::BLESSE, $hero->getStatut(), 'en attente de validation');
        self::assertTrue($sejour->isActif());
        self::assertCount(1, array_filter($this->persistes, static fn ($o) => $o instanceof Alerte && 'Récupération terminée' === $o->getTitre()));

        // Passage suivant : aucune nouvelle alerte, aucune transition.
        self::assertSame([], $service->actualiser($sejour, self::a('+3 hours'), new ContexteRecuperation()));
        self::assertCount(1, array_filter($this->persistes, static fn ($o) => $o instanceof Alerte && 'Récupération terminée' === $o->getTitre()));

        $service->sortir($sejour, self::a('+3 hours'));
        self::assertSame(StatutSejour::SORTI, $sejour->getStatut());
        self::assertFalse($sejour->isActif());
        self::assertNull($sejour->getCleActive());
        self::assertSame(HeroStatut::DISPONIBLE, $hero->getStatut());
    }

    public function testSortieAutomatique(): void
    {
        $service = $this->service(new InfirmerieConfiguration(sortieAutomatique: true));
        $hero = $this->hero(90, 20);
        $sejour = $this->enTraitement($hero);
        self::assertSame(['retabli', 'sorti'], $service->actualiser($sejour, self::a('+1 hour'), new ContexteRecuperation()));
        self::assertSame(StatutSejour::SORTI, $sejour->getStatut());
        self::assertEquals(self::a('+25 minutes'), $sejour->getSortiLe());
        self::assertSame(HeroStatut::DISPONIBLE, $hero->getStatut());
    }

    public function testSortieRefuseeAvantRetablissement(): void
    {
        $sejour = $this->enTraitement($this->hero());
        $this->expectException(InfirmerieException::class);
        $this->service()->sortir($sejour, self::a('+1 minute'));
    }

    public function testRetourAuQgPuisRecuperationApresInterruption(): void
    {
        $service = $this->service();
        $hero = $this->hero(40, 50);
        $sejour = $this->sejour($hero);
        $lyon = new Coordonnees(45.7640, 4.8357);
        $sejour->commencerRetour($lyon, 392.0, 196.0, self::a('+0 sec'), self::a('+2 hours'));

        // Pendant le retour : position intermédiaire, aucune récupération.
        self::assertSame([], $service->actualiser($sejour, self::a('+1 hour'), new ContexteRecuperation()));
        self::assertSame(StatutSejour::RETOUR_QG, $sejour->getStatut());
        self::assertEqualsWithDelta(40.0, $sejour->getSanteActuelle(), 1e-9);
        $milieu = $service->positionRetour($sejour, self::a('+1 hour'));
        self::assertNotNull($milieu);
        self::assertEqualsWithDelta(0.5, $service->progressionRetour($sejour, self::a('+1 hour')), 0.01);
        self::assertGreaterThan(45.7640, $milieu->latitude);

        // Worker arrêté pendant 2 h 25 : l'arrivée est actée à l'heure prévue, la récupération
        // court depuis l'arrivée (25 min = +10 PV), puis le héros est repositionné au QG.
        self::assertSame(['arrivee'], $service->actualiser($sejour, self::a('+2 hours 25 minutes'), new ContexteRecuperation()));
        self::assertSame(StatutSejour::EN_TRAITEMENT, $sejour->getStatut());
        self::assertEquals(self::a('+2 hours'), $sejour->getArriveeQgLe());
        self::assertEquals(self::a('+2 hours'), $sejour->getDebutTraitementLe());
        self::assertEqualsWithDelta(50.0, $sejour->getSanteActuelle(), 1e-6);
        self::assertTrue((new QgConfiguration())->coordonnees()->egale($hero->getCoordonnees()));
    }
}
