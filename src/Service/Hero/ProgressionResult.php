<?php

namespace App\Service\Hero;

final readonly class ProgressionResult
{
    public function __construct(
        public int $niveauAvant,
        public int $niveauApres,
        public int $experienceAjoutee,
    ) {
    }

    public function niveauxGagnes(): int
    {
        return $this->niveauApres - $this->niveauAvant;
    }
}
