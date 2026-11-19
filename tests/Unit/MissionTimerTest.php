<?php

namespace App\Tests\Unit;

use App\Service\Mission\MissionTimer;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class MissionTimerTest extends TestCase
{
    public function testTempsRestantCalculeDepuisDateFinPrevue(): void
    {
        $clock = new MockClock('2026-11-19 12:00:00');
        $timer = new MissionTimer($clock);
        $mission = Builder::mission(['fin' => new \DateTimeImmutable('2026-11-19 12:14:32')]);

        self::assertSame(14 * 60 + 32, $timer->secondesRestantes($mission));
        self::assertSame('00:14:32', $timer->snapshot($mission)->libelle);
        self::assertFalse($timer->estEchu($mission));
    }

    public function testLeTempsDecroitAvecLHorloge(): void
    {
        $clock = new MockClock('2026-11-19 12:00:00');
        $timer = new MissionTimer($clock);
        $mission = Builder::mission(['fin' => new \DateTimeImmutable('2026-11-19 13:00:00')]);

        $clock->sleep(600);
        self::assertSame(3000, $timer->secondesRestantes($mission));
    }

    public function testJamaisNegatifEtLibelleMissionTerminee(): void
    {
        $clock = new MockClock('2026-11-19 12:00:00');
        $timer = new MissionTimer($clock);
        $mission = Builder::mission(['fin' => new \DateTimeImmutable('2026-11-19 11:00:00')]);

        $snapshot = $timer->snapshot($mission);
        self::assertSame(0, $snapshot->secondesRestantes);
        self::assertTrue($snapshot->termine);
        self::assertSame('Mission terminée', $snapshot->libelle);
    }

    public function testFormatageAuDelaDe24Heures(): void
    {
        $timer = new MissionTimer(new MockClock());
        self::assertSame('26:00:05', $timer->formater(26 * 3600 + 5));
        self::assertSame(MissionTimer::LIBELLE_TERMINE, $timer->formater(-10));
    }
}
