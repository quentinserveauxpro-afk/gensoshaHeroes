<?php

namespace App\Simulation;

/** Aléa constant, pour des tests stables. */
final class FixedRandomGenerator implements RandomGeneratorInterface
{
    public function __construct(private readonly float $valeur = 0.5)
    {
        if ($valeur < 0 || $valeur >= 1) {
            throw new \InvalidArgumentException('La valeur doit appartenir à [0, 1).');
        }
    }

    public function valeur(string $graine): float
    {
        return $this->valeur;
    }
}
