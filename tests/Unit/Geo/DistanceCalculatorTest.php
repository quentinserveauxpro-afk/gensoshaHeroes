<?php

namespace App\Tests\Unit\Geo;

use App\Geo\Coordonnees;
use App\Geo\DistanceCalculator;
use PHPUnit\Framework\TestCase;

final class DistanceCalculatorTest extends TestCase
{
    private DistanceCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new DistanceCalculator();
    }

    public function testHaversineParisLyon(): void
    {
        $d = $this->calc->distanceKm(new Coordonnees(48.8566, 2.3522), new Coordonnees(45.7640, 4.8357));
        self::assertEqualsWithDelta(391.5, $d, 1.5);
    }

    public function testHaversineParisMarseille(): void
    {
        $d = $this->calc->distanceKm(new Coordonnees(48.8566, 2.3522), new Coordonnees(43.2965, 5.3698));
        self::assertEqualsWithDelta(661.0, $d, 2.0);
    }

    public function testDistanceNulleEtSymetrique(): void
    {
        $a = new Coordonnees(16.2411, -61.5331);
        $b = new Coordonnees(-20.8823, 55.4504);
        self::assertSame(0.0, $this->calc->distanceKm($a, $a));
        self::assertEqualsWithDelta($this->calc->distanceKm($a, $b), $this->calc->distanceKm($b, $a), 1e-9);
    }

    public function testInterpolationAuxBornesEtAuMilieu(): void
    {
        $paris = new Coordonnees(48.8566, 2.3522);
        $lyon = new Coordonnees(45.7640, 4.8357);
        self::assertTrue($this->calc->pointIntermediaire($paris, $lyon, 0.0)->egale($paris, 1e-6));
        self::assertTrue($this->calc->pointIntermediaire($paris, $lyon, 1.0)->egale($lyon, 1e-6));

        $milieu = $this->calc->pointIntermediaire($paris, $lyon, 0.5);
        $total = $this->calc->distanceKm($paris, $lyon);
        self::assertEqualsWithDelta($total / 2, $this->calc->distanceKm($paris, $milieu), 0.5);
        self::assertEqualsWithDelta($total / 2, $this->calc->distanceKm($milieu, $lyon), 0.5);
    }

    public function testInterpolationBorneLaFraction(): void
    {
        $a = new Coordonnees(48.0, 2.0);
        $b = new Coordonnees(47.0, 3.0);
        self::assertTrue($this->calc->pointIntermediaire($a, $b, 1.7)->egale($b, 1e-6));
        self::assertTrue($this->calc->pointIntermediaire($a, $b, -0.3)->egale($a, 1e-6));
    }

    public function testCoordonneesInvalidesRefusees(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Coordonnees(95.0, 2.0);
    }

    public function testConversionDecimaleDoctrine(): void
    {
        $c = Coordonnees::depuisDecimaux('48.8566000', '2.3522000');
        self::assertSame('48.8566000', $c->latitudeDecimale());
        self::assertSame(48.8566, $c->latitude);
    }
}
