<?php

namespace App\Infirmerie;

/** Conditions de l'infirmerie au moment du calcul (évaluées une fois par passage). */
final readonly class ContexteRecuperation
{
    /** @param list<int> $soutiensDisponibles identifiants des héros de soutien médical disponibles au QG */
    public function __construct(
        public array $soutiensDisponibles = [],
        public int $occupation = 1,
    ) {
    }

    /** Un soutien autre que le patient lui-même est-il disponible ? */
    public function soutienPour(int $heroId): bool
    {
        return [] !== array_diff($this->soutiensDisponibles, [$heroId]);
    }
}
