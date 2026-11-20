<?php

namespace App\Simulation;

/** Compte rendu d'un passage du moteur sur une mission. */
final readonly class RapportSimulation
{
    /** @param list<string> $evenements */
    public function __construct(
        public int $missionId,
        public string $phase,
        public int $etapes,
        public bool $terminee = false,
        public ?bool $succes = null,
        public array $evenements = [],
    ) {
    }
}
