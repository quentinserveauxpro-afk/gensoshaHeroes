<?php

namespace App\Tests\Unit\GameTime;

use App\Entity\SejourInfirmerie;
use App\Enum\MotifInfirmerie;
use App\Enum\TypeAdmission;
use App\GameTime\GameTimeConfiguration;
use App\GameTime\InfirmaryRecoveryTimeCalculator;
use App\Infirmerie\ContexteRecuperation;
use App\Infirmerie\InfirmerieConfiguration;
use App\Service\Mission\EffectiveStatsResolver;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;

/** Durée d'un soin : 1 à 15 min selon l'état à l'admission. */
final class InfirmaryRecoveryTimeCalculatorTest extends TestCase
{
    private InfirmaryRecoveryTimeCalculator $calc;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->calc = new InfirmaryRecoveryTimeCalculator(new GameTimeConfiguration(), new InfirmerieConfiguration(), new EffectiveStatsResolver());
        $this->now = new \DateTimeImmutable('2026-11-20 10:00:00');
    }

    private function duree(int $pv, int $fatigue, int $vigueur = 50, MotifInfirmerie $motif = MotifInfirmerie::DECISION_MEDICALE, ?ContexteRecuperation $ctx = null, array $o = []): int
    {
        $hero = Builder::hero($o + ['pv' => $pv, 'pvMax' => 100, 'fatigue' => $fatigue, 'vigueur' => $vigueur]);

        return $this->calc->duree(new SejourInfirmerie($hero, $motif, TypeAdmission::AUTOMATIQUE, 100, 20, $this->now), $ctx ?? new ContexteRecuperation(), $this->now);
    }

    public function testFormuleDeReference(): void
    {
        // gravité = 0,6 × 0,6 + 0,5 × 0,4 = 0,56 → 60 + 0,56 × 840 = 530,4 → 530 s.
        self::assertSame(530, $this->duree(40, 50));
    }

    public function testSoinLeger(): void
    {
        $d = $this->duree(80, 10, 80);
        self::assertGreaterThanOrEqual(60, $d);
        self::assertLessThanOrEqual(240, $d, 'blessure légère : 1 à 4 min');
    }

    public function testEpuisementImportant(): void
    {
        $d = $this->duree(100, 95, 80, MotifInfirmerie::EPUISEMENT);
        self::assertGreaterThanOrEqual(300, $d);
        self::assertLessThanOrEqual(600, $d, 'fatigue importante : 5 à 10 min');
    }

    public function testSoinCritique(): void
    {
        $d = $this->duree(15, 30, 80, MotifInfirmerie::SANTE_CRITIQUE);
        self::assertGreaterThanOrEqual(600, $d);
        self::assertLessThanOrEqual(900, $d, 'santé critique : 10 à 15 min');
    }

    public function testPlafondDe15Minutes(): void
    {
        self::assertSame(900, $this->duree(0, 100, 0, MotifInfirmerie::SANTE_CRITIQUE));
        self::assertSame(900, $this->duree(0, 100, 0, MotifInfirmerie::SANTE_CRITIQUE, new ContexteRecuperation([], 50)), 'saturée : toujours plafonnée');
    }

    public function testMinimumDUneMinute(): void
    {
        self::assertSame(60, $this->duree(100, 0, 100));
        self::assertSame(60, $this->duree(100, 0, 100, ctx: new ContexteRecuperation([7])), 'le soutien ne passe jamais sous le minimum');
    }

    public function testBonusDeSupportMedicalParAptitude(): void
    {
        $sans = $this->duree(40, 50);
        self::assertSame((int) round(530.4 * 0.85), $this->duree(40, 50, ctx: new ContexteRecuperation([4242])));
        self::assertSame((int) round(530.4 * 0.925), $this->duree(40, 50, o: ['aptitudes' => [Builder::aptitude('SUPPORT_MEDICAL')]]), 'patient soignant : demi-effet');
        self::assertLessThan($sans, $this->duree(40, 50, ctx: new ContexteRecuperation([4242])));
    }

    public function testVigueurGraviteEtSaturation(): void
    {
        self::assertSame((int) round(530.4 * 0.9), $this->duree(40, 50, 100));
        self::assertSame((int) round(530.4 * 1.1), $this->duree(40, 50, ctx: new ContexteRecuperation([], 7)), '7 patients pour 6 lits');
        self::assertSame(530, $this->duree(40, 50, ctx: new ContexteRecuperation([], 6)), 'capacité atteinte, pas dépassée');
        self::assertSame((int) round(530.4 * 1.1), $this->duree(40, 50, motif: MotifInfirmerie::BLESSURE));
    }
}
