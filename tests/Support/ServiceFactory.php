<?php

namespace App\Tests\Support;

use App\Service\Map\PositionDistanceCalculator;
use App\Service\Mission\EffectiveStatsResolver;
use App\Service\Mission\MissionSuccessCalculator;
use App\Service\Mission\SynergyResolver;
use Symfony\Component\Clock\MockClock;

/** Construit les services purs (sans conteneur) pour les tests unitaires. */
final class ServiceFactory
{
    public static function calculator(?MockClock $clock = null): MissionSuccessCalculator
    {
        return new MissionSuccessCalculator(
            new PositionDistanceCalculator(),
            new EffectiveStatsResolver(),
            new SynergyResolver(),
            $clock ?? new MockClock(),
        );
    }
}
