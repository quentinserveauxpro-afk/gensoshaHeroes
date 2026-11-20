<?php

namespace App\Tests\Unit\Infirmerie;

use App\Enum\MotifInfirmerie;
use App\Infirmerie\HeroMedicalEvaluationService;
use App\Infirmerie\InfirmerieConfiguration;
use App\Tests\Support\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HeroMedicalEvaluationServiceTest extends TestCase
{
    private HeroMedicalEvaluationService $service;

    protected function setUp(): void
    {
        $this->service = new HeroMedicalEvaluationService(new InfirmerieConfiguration());
    }

    public static function pourcentages(): iterable
    {
        yield 'nominal' => [50, 200, 25.0];
        yield 'plein' => [200, 200, 100.0];
        yield 'maximum nul' => [10, 0, 0.0];
        yield 'maximum négatif' => [10, -5, 0.0];
        yield 'pv manquants' => [null, 100, 0.0];
        yield 'maximum manquant' => [10, null, 0.0];
        yield 'pv négatifs' => [-20, 100, 0.0];
        yield 'au-delà du maximum' => [150, 100, 100.0];
    }

    #[DataProvider('pourcentages')]
    public function testPourcentageDeSante(?int $pv, ?int $max, float $attendu): void
    {
        self::assertSame($attendu, HeroMedicalEvaluationService::santePourcentage($pv, $max));
    }

    public function testBornesDeSanteEtDeFatigue(): void
    {
        self::assertSame(0.0, HeroMedicalEvaluationService::bornerSante(-3, 100));
        self::assertSame(100.0, HeroMedicalEvaluationService::bornerSante(130, 100));
        self::assertSame(0.0, HeroMedicalEvaluationService::bornerSante(5, -1));
        self::assertSame(0.0, HeroMedicalEvaluationService::bornerFatigue(-10));
        self::assertSame(100.0, HeroMedicalEvaluationService::bornerFatigue(140));
        self::assertSame(0.0, HeroMedicalEvaluationService::bornerFatigue(null));
    }

    public function testSanteCritiqueAuSeuilInclusEtAZero(): void
    {
        self::assertTrue($this->service->estSanteCritique(Builder::hero(['pv' => 20, 'pvMax' => 100])), '20 % est critique (≤)');
        self::assertFalse($this->service->estSanteCritique(Builder::hero(['pv' => 21, 'pvMax' => 100])));
        self::assertTrue($this->service->estSanteCritique(Builder::hero(['pv' => 0, 'pvMax' => 100])));
        self::assertTrue($this->service->estSanteCritique(Builder::hero(['pv' => 0, 'pvMax' => 0])), 'maximum nul : 0 PV');
    }

    public function testFatigueCritiqueAuSeuilInclus(): void
    {
        self::assertTrue($this->service->estFatigueCritique(Builder::hero(['fatigue' => 90])));
        self::assertFalse($this->service->estFatigueCritique(Builder::hero(['fatigue' => 89])));
    }

    public function testMotifDeRapatriementEtPriorite(): void
    {
        self::assertNull($this->service->motifRapatriement(Builder::hero(['pv' => 80, 'fatigue' => 10])));
        self::assertSame(MotifInfirmerie::EPUISEMENT, $this->service->motifRapatriement(Builder::hero(['pv' => 80, 'fatigue' => 95])));
        self::assertSame(MotifInfirmerie::SANTE_CRITIQUE, $this->service->motifRapatriement(Builder::hero(['pv' => 10, 'fatigue' => 95])), 'la santé prime');
    }

    public function testSeuilsConfigurablesSansModifierLeCode(): void
    {
        $strict = new HeroMedicalEvaluationService(new InfirmerieConfiguration(santeCritique: 50.0, fatigueCritique: 60.0, santeSortie: 80.0, fatigueSortie: 30.0));
        self::assertTrue($strict->estSanteCritique(Builder::hero(['pv' => 45, 'pvMax' => 100])));
        self::assertTrue($strict->estFatigueCritique(Builder::hero(['fatigue' => 60])));
        self::assertSame(80, $strict->santeCible(Builder::hero(['pvMax' => 100])));
        self::assertSame(30, $strict->fatigueCible());
    }

    public function testCiblesDeSortieEtRetablissement(): void
    {
        self::assertSame(333, $this->service->santeCible(Builder::hero(['pvMax' => 333])));
        self::assertSame(20, $this->service->fatigueCible());
        self::assertTrue($this->service->estRetabli(333.0, 20.0, 333, 20));
        self::assertFalse($this->service->estRetabli(332.5, 20.0, 333, 20));
        self::assertFalse($this->service->estRetabli(333.0, 20.5, 333, 20));
    }
}
