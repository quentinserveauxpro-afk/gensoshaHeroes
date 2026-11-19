<?php

namespace App\Tests\Unit;

use App\Enum\Statistique;
use App\Service\Mission\EffectiveStatsResolver;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;

final class EffectiveStatsResolverTest extends TestCase
{
    public function testBonusCumulablesEtNonCumulables(): void
    {
        $hero = Builder::hero(['combat' => 50]);
        Builder::bonus($hero, Statistique::COMBAT, 5);
        Builder::bonus($hero, Statistique::COMBAT, 3);
        Builder::bonus($hero, Statistique::COMBAT, 8, false);
        Builder::bonus($hero, Statistique::COMBAT, 6, false);

        $bonus = (new EffectiveStatsResolver())->bonusParStatistique($hero, new \DateTimeImmutable());
        // 5 + 3 cumulables + max(8, 6) non cumulables
        self::assertSame(16, $bonus['COMBAT']);
    }

    public function testBonusExpireOuFuturOuInactifIgnore(): void
    {
        $hero = Builder::hero(['mobilite' => 50]);
        Builder::bonus($hero, Statistique::MOBILITE, 10, true, '-1 hour', '-2 days');
        Builder::bonus($hero, Statistique::MOBILITE, 10, true, null, '+1 day');
        Builder::bonus($hero, Statistique::MOBILITE, 10)->setActif(false);

        $stats = (new EffectiveStatsResolver())->statsEffectives($hero, new \DateTimeImmutable());
        self::assertSame(50, $stats['MOBILITE']);
    }

    public function testStatEffectiveBorneeA100(): void
    {
        $hero = Builder::hero(['vigueur' => 95]);
        Builder::bonus($hero, Statistique::VIGUEUR, 20);

        self::assertSame(100, (new EffectiveStatsResolver())->statsEffectives($hero, new \DateTimeImmutable())['VIGUEUR']);
    }
}
