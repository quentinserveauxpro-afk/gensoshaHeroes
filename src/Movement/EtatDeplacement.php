<?php

namespace App\Movement;

use App\Geo\Coordonnees;

/** Position calculée par le serveur d'un déplacement à un instant donné. */
final readonly class EtatDeplacement
{
    public function __construct(
        public Coordonnees $position,
        public float $fraction,
        public float $distanceRestanteKm,
        public int $secondesRestantes,
        public bool $arrive,
    ) {
    }

    public function progression(): float
    {
        return round($this->fraction * 100, 2);
    }
}
