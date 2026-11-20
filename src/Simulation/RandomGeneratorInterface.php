<?php

namespace App\Simulation;

/**
 * Source d'aléa du moteur, injectable et remplaçable dans les tests.
 * valeur() doit être déterministe pour une même graine afin qu'une étape recalculée
 * (reprise après incident) produise exactement le même résultat.
 */
interface RandomGeneratorInterface
{
    /** Nombre dans [0, 1) dérivé de la graine. */
    public function valeur(string $graine): float;
}
