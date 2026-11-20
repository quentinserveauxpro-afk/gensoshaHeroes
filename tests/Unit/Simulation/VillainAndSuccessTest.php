<?php

namespace App\Tests\Unit\Simulation;

use App\Simulation\FixedRandomGenerator;
use App\Simulation\HashRandomGenerator;
use App\Simulation\LiveSuccessRateCalculator;
use App\Simulation\VillainThreatCalculator;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;

final class VillainAndSuccessTest extends TestCase
{
    public function testLesVilainsAugmententLaMenace(): void
    {
        $calc = new VillainThreatCalculator();
        $puissance = ['COMBAT' => 60, 'INTELLIGENCE' => 60, 'CHARISME' => 60, 'MOBILITE' => 60, 'VIGUEUR' => 60];
        $calme = Builder::mission();
        $dangereuse = Builder::mission()->addVillain(Builder::villain(9, 90, 80))->addVillain(Builder::villain(7, 70, 60));

        self::assertGreaterThan($calc->menaceInitiale($calme, $puissance), $calc->menaceInitiale($dangereuse, $puissance));
    }

    public function testLaMenaceDecroitAvecLaProgression(): void
    {
        $calc = new VillainThreatCalculator();
        self::assertSame(80.0, $calc->menaceCourante(80.0, 0));
        self::assertEqualsWithDelta(80.0 * (1 - VillainThreatCalculator::REDUCTION_MAX), $calc->menaceCourante(80.0, 100), 1e-9);
        self::assertLessThan($calc->menaceCourante(80.0, 20), $calc->menaceCourante(80.0, 60));
    }

    public function testTauxDeReussiteEnDirect(): void
    {
        $calc = new LiveSuccessRateCalculator();
        self::assertSame(100, $calc->calculer(40, 1, 10, 0.5, true, true));
        self::assertSame(0, $calc->calculer(40, 1, 10, 0.5, false, false));

        $confortable = $calc->calculer(50, 2.0, 100, 1.0, false, true);
        $serre = $calc->calculer(50, 0.1, 10, 1.0, false, true);
        $blesses = $calc->calculer(50, 2.0, 100, 0.1, false, true);
        self::assertGreaterThan($serre, $confortable);
        self::assertGreaterThan($blesses, $confortable);
        self::assertGreaterThanOrEqual(0, $serre);
        self::assertLessThanOrEqual(100, $confortable);
    }

    public function testGenerateurAleatoireInjectableEtDeterministe(): void
    {
        $rng = new HashRandomGenerator();
        self::assertSame($rng->valeur('mission:1:etape:3:hero:2'), $rng->valeur('mission:1:etape:3:hero:2'));
        self::assertNotSame($rng->valeur('mission:1:etape:3:hero:2'), $rng->valeur('mission:1:etape:4:hero:2'));
        $v = $rng->valeur('x');
        self::assertGreaterThanOrEqual(0.0, $v);
        self::assertLessThan(1.0, $v);
        self::assertSame(0.25, (new FixedRandomGenerator(0.25))->valeur('peu importe'));
    }
}
