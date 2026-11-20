<?php

namespace App\Tests\Unit\Simulation;

use App\Enum\Difficulte;
use App\Simulation\HeroHealthCalculator;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;

final class HeroHealthCalculatorTest extends TestCase
{
    private HeroHealthCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new HeroHealthCalculator();
    }

    public function testDegatsDeterministesPourUnMemeAlea(): void
    {
        $stats = ['VIGUEUR' => 50, 'COMBAT' => 50];
        self::assertSame($this->calc->degats($stats, 80, Difficulte::B, 0.4), $this->calc->degats($stats, 80, Difficulte::B, 0.4));
    }

    public function testVigueurEtCombatReduisentLesDegats(): void
    {
        $fragile = $this->calc->degats(['VIGUEUR' => 10, 'COMBAT' => 10], 100, Difficulte::A, 1.0);
        $robuste = $this->calc->degats(['VIGUEUR' => 95, 'COMBAT' => 95], 100, Difficulte::A, 1.0);
        self::assertGreaterThan($robuste, $fragile);
    }

    public function testMenaceEtDifficulteAugmententLesDegats(): void
    {
        $stats = ['VIGUEUR' => 30, 'COMBAT' => 30];
        self::assertGreaterThan($this->calc->degats($stats, 30, Difficulte::C, 0.5), $this->calc->degats($stats, 120, Difficulte::C, 0.5));
        self::assertGreaterThanOrEqual($this->calc->degats($stats, 120, Difficulte::D, 0.5), $this->calc->degats($stats, 120, Difficulte::S, 0.5));
        self::assertSame(0, $this->calc->degats($stats, 0, Difficulte::S, 1.0));
    }

    public function testPointsDeVieBornesEntreZeroEtMaximum(): void
    {
        $hero = Builder::hero(['pv' => 10, 'pvMax' => 100]);
        self::assertSame(0, $this->calc->appliquer($hero, 500));
        self::assertSame(0, $hero->getPointsDeVie());
        self::assertSame(100, $this->calc->appliquer($hero, -1000));
        self::assertSame(0, HeroHealthCalculator::borner(-5, 100));
        self::assertSame(100, HeroHealthCalculator::borner(250, 100));
    }

    public function testHerosAZeroNeContribuePasEtSeuilCritique(): void
    {
        $ko = Builder::hero(['pv' => 0, 'pvMax' => 100]);
        $critique = Builder::hero(['pv' => 20, 'pvMax' => 100]);
        $ok = Builder::hero(['pv' => 80, 'pvMax' => 100]);

        self::assertFalse($this->calc->peutContribuer($ko));
        self::assertTrue($this->calc->peutContribuer($critique));
        self::assertTrue($this->calc->estCritique($critique));
        self::assertFalse($this->calc->estCritique($ok));
        self::assertNotSame('', $this->calc->libelle($critique));
    }
}
