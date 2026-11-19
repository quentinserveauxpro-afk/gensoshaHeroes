<?php

namespace App\Service\Hero;

use App\Entity\Hero;

final readonly class HeroRecommendation
{
    /** @param list<string> $raisons */
    public function __construct(
        public Hero $hero,
        public int $score,
        public bool $eligible,
        public float $distance,
        public array $raisons,
        public ?string $motifIneligibilite = null,
    ) {
    }
}
