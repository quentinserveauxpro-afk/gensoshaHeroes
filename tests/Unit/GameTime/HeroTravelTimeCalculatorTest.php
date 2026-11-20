<?php

namespace App\Tests\Unit\GameTime;

use App\Enum\Statistique;
use App\GameTime\GameTimeConfiguration;
use App\GameTime\HeroTravelTimeCalculator;
use App\Service\Mission\EffectiveStatsResolver;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;

/** Durée accélérée des trajets : 30 s à 10 min, déterministe, jamais négative. */
final class HeroTravelTimeCalculatorTest extends TestCase
{
    private HeroTravelTimeCalculator $calc;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->calc = new HeroTravelTimeCalculator(new GameTimeConfiguration(), new EffectiveStatsResolver());
        $this->now = new \DateTimeImmutable('2026-11-20 10:00:00');
    }

    /** Héros neutre : mobilité 50 (×1), fatigue 0, santé 100 %. */
    private function duree(float $km, array $o = []): int
    {
        return $this->calc->duree(Builder::hero($o + ['mobilite' => 50, 'fatigue' => 0, 'pv' => 100, 'pvMax' => 100]), $km, $this->now);
    }

    public function testFormuleDeReference(): void
    {
        // base = 30 + 0,5 × 570 = 315 s ; tous les modificateurs valent 1.
        self::assertSame(315, $this->duree(500));
    }

    public function testDejaSurPlaceArriveeImmediate(): void
    {
        self::assertSame(0, $this->duree(0.0));
        self::assertSame(0, $this->duree(0.04));
    }

    public function testDeplacementTresCourtAuMinimumDe30Secondes(): void
    {
        self::assertSame(30, $this->duree(0.5, ['mobilite' => 100]));
        self::assertSame(30, $this->duree(1.0, ['mobilite' => 100]));
    }

    public function testDeplacementTresLongPlafonneA10Minutes(): void
    {
        self::assertSame(600, $this->duree(9000));
        self::assertSame(600, $this->duree(9000, ['mobilite' => 0, 'fatigue' => 100, 'pv' => 5]), 'pire cas plafonné');
        self::assertSame(600, $this->duree(1000));
    }

    public function testMobiliteFaibleEtElevee(): void
    {
        $lent = $this->duree(400, ['mobilite' => 0]);
        $rapide = $this->duree(400, ['mobilite' => 100]);
        self::assertSame((int) round((30 + 0.4 * 570) * 1.2), $lent);
        self::assertSame((int) round((30 + 0.4 * 570) * 0.8), $rapide);
    }

    public function testBonusDeVitesseActif(): void
    {
        $hero = Builder::hero(['mobilite' => 50, 'fatigue' => 0]);
        $sans = $this->calc->duree($hero, 400, $this->now);
        Builder::bonus($hero, Statistique::MOBILITE, 40);
        self::assertLessThan($sans, $this->calc->duree($hero, 400, $this->now));
    }

    public function testCapaciteDeVol(): void
    {
        $volant = $this->duree(400, ['aptitudes' => [Builder::aptitude('VOL')]]);
        self::assertSame((int) round((30 + 0.4 * 570) * 0.75), $volant);
        self::assertLessThan($this->duree(400), $volant);
    }

    public function testFatigueEleveeEtSanteCritiqueRalentissent(): void
    {
        $base = $this->duree(400);
        self::assertSame((int) round((30 + 0.4 * 570) * 1.3), $this->duree(400, ['fatigue' => 100]));
        self::assertSame((int) round((30 + 0.4 * 570) * 1.18), $this->duree(400, ['pv' => 10]));
        self::assertGreaterThan($base, $this->duree(400, ['fatigue' => 90, 'pv' => 15]));
    }

    public function testRetourAuQgRespecteLaMemeLimite(): void
    {
        // Seuil « sur place » du QG : 1 km ; La Réunion → Paris reste plafonné.
        $hero = Builder::hero(['pv' => 10, 'fatigue' => 95]);
        self::assertSame(0, $this->calc->duree($hero, 0.8, $this->now, 1.0));
        self::assertSame(600, $this->calc->duree($hero, 9300, $this->now, 1.0));
    }

    public function testJamaisNegatifNiInfini(): void
    {
        self::assertSame(0, $this->duree(-12));
        self::assertSame(0, $this->duree(NAN));
        self::assertSame(0, $this->duree(INF));
        self::assertSame(600, HeroTravelTimeCalculator::borner(INF, 30, 600));
        self::assertSame(30, HeroTravelTimeCalculator::borner(-50, 30, 600));
        self::assertSame(0.0, HeroTravelTimeCalculator::vitesseEquivalente(100, 0));
    }

    public function testDeterministe(): void
    {
        self::assertSame($this->duree(321.5, ['mobilite' => 37]), $this->duree(321.5, ['mobilite' => 37]));
    }

    public function testConfigurationIncoherenteBornee(): void
    {
        $calc = new HeroTravelTimeCalculator(new GameTimeConfiguration(trajetMin: -10, trajetMax: -20), new EffectiveStatsResolver());
        self::assertSame(0, $calc->duree(Builder::hero(), 500, $this->now));
    }
}
