<?php

namespace App\Tests\Unit\Geo;

use App\Geo\EstimatedArrivalCalculator;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;

final class EstimatedArrivalCalculatorTest extends TestCase
{
    public function testVitesseDependDeLaMobiliteEtDeLaFatigue(): void
    {
        $calc = new EstimatedArrivalCalculator(1.0);
        $rapide = $calc->vitessePourHero(Builder::hero(['mobilite' => 100, 'fatigue' => 0]));
        $lent = $calc->vitessePourHero(Builder::hero(['mobilite' => 20, 'fatigue' => 0]));
        $fatigue = $calc->vitessePourHero(Builder::hero(['mobilite' => 100, 'fatigue' => 100]));

        self::assertSame(300.0, $rapide);
        self::assertSame(108.0, $lent);
        self::assertSame(210.0, $fatigue);
    }

    public function testDureeEtDateArrivee(): void
    {
        $calc = new EstimatedArrivalCalculator(1.0);
        self::assertSame(3600, $calc->dureeSecondes(150.0, 150.0));
        $depart = new \DateTimeImmutable('2026-11-20 10:00:00');
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:30:00'), $calc->dateArrivee($depart, 100.0, 200.0));
    }

    public function testFacteurAccelerationReduitLaDuree(): void
    {
        self::assertSame(60, (new EstimatedArrivalCalculator(60.0))->dureeSecondes(150.0, 150.0));
    }

    public function testFractionEtTempsRestantBornes(): void
    {
        $calc = new EstimatedArrivalCalculator(1.0);
        $depart = new \DateTimeImmutable('2026-11-20 10:00:00');
        $arrivee = new \DateTimeImmutable('2026-11-20 11:00:00');

        self::assertSame(0.0, $calc->fraction($depart, $arrivee, $depart->modify('-5 minutes')));
        self::assertEqualsWithDelta(0.25, $calc->fraction($depart, $arrivee, $depart->modify('+15 minutes')), 1e-9);
        self::assertSame(1.0, $calc->fraction($depart, $arrivee, $arrivee->modify('+1 hour')));
        self::assertSame(2700, $calc->secondesRestantes($arrivee, $depart->modify('+15 minutes')));
        self::assertSame(0, $calc->secondesRestantes($arrivee, $arrivee->modify('+1 minute')));
    }
}
