<?php

namespace App\Simulation;

final readonly class ResultatProgression
{
    public function __construct(
        public float $gain,
        public bool $bloque,
        public ?string $raison = null,
    ) {
    }
}
