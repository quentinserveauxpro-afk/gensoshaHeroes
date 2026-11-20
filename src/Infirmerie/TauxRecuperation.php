<?php

namespace App\Infirmerie;

/** Taux de récupération (par minute simulée) et détail des modificateurs appliqués. */
final readonly class TauxRecuperation
{
    /** @param array<string, float> $facteurs */
    public function __construct(
        public float $santeParMinute,
        public float $fatigueParMinute,
        public array $facteurs = [],
    ) {
    }
}
