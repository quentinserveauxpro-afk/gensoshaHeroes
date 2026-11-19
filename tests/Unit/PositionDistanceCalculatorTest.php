<?php

namespace App\Tests\Unit;

use App\Service\Map\PositionDistanceCalculator;
use App\Tests\Support\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PositionDistanceCalculatorTest extends TestCase
{
    /** @return iterable<string, array{float, float, float, float, float}> */
    public static function points(): iterable
    {
        yield 'même point' => [10, 10, 10, 10, 0.0];
        yield 'triangle 3-4-5' => [0, 0, 3, 4, 5.0];
        yield 'coordonnées décimales' => [1.5, 2.5, 4.5, 6.5, 5.0];
        yield 'symétrie négative' => [5, 5, 2, 1, 5.0];
    }

    #[DataProvider('points')]
    public function testDistanceEuclidienne(float $x1, float $y1, float $x2, float $y2, float $attendu): void
    {
        self::assertEqualsWithDelta($attendu, (new PositionDistanceCalculator())->distance($x1, $y1, $x2, $y2), 1e-9);
    }

    public function testHeroMission(): void
    {
        $calc = new PositionDistanceCalculator();
        $hero = Builder::hero(['x' => 10.0, 'y' => 20.0]);
        $mission = Builder::mission(['x' => 13.0, 'y' => 24.0]);

        self::assertEqualsWithDelta(5.0, $calc->heroMission($hero, $mission), 1e-9);
        self::assertSame(5.0, $calc->heroMissionArrondie($hero, $mission));
    }

    public function testPresenceALaBase(): void
    {
        $calc = new PositionDistanceCalculator();
        self::assertTrue($calc->isALaBase(Builder::hero(['x' => 50.5, 'y' => 50.5])));
        self::assertFalse($calc->isALaBase(Builder::hero(['x' => 60.0, 'y' => 50.0])));
    }
}
