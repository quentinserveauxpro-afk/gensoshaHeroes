<?php

namespace App\Simulation;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** Aléa déterministe : empreinte xxh64 de la graine ramenée sur [0, 1). */
#[AsAlias(RandomGeneratorInterface::class)]
final class HashRandomGenerator implements RandomGeneratorInterface
{
    public function valeur(string $graine): float
    {
        $entier = hexdec(substr(hash('xxh64', $graine), 0, 13)); // 52 bits : exact en float

        return $entier / 2 ** 52;
    }
}
