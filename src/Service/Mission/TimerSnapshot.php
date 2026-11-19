<?php

namespace App\Service\Mission;

/** Valeur de référence du compte à rebours, calculée côté serveur. */
final readonly class TimerSnapshot
{
    public function __construct(
        public int $secondesRestantes,
        public bool $termine,
        public ?\DateTimeImmutable $echeance,
        public string $libelle,
    ) {
    }
}
