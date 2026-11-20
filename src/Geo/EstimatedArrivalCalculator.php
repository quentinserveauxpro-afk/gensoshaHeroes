<?php

namespace App\Geo;

use App\Entity\Hero;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Vitesse de déplacement des héros et heure d'arrivée estimée.
 *
 * Vitesse (km/h) = VITESSE_BASE + COEF_MOBILITE × mobilité, soit 60 km/h (mobilité 0)
 * à 300 km/h (mobilité 100), réduite de 30 % au maximum par la fatigue.
 *
 * Le facteur d'accélération (SIMULATION_FACTEUR_TEMPS, 1 = temps réel) permet les démonstrations :
 * avec un facteur 60, une minute réelle représente une heure de trajet.
 */
final class EstimatedArrivalCalculator
{
    public const VITESSE_BASE = 60.0;
    public const COEF_MOBILITE = 2.4;
    public const REDUCTION_FATIGUE_MAX = 0.30;

    private readonly float $facteurTemps;

    public function __construct(
        #[Autowire('%env(float:SIMULATION_FACTEUR_TEMPS)%')]
        float $facteurTemps = 1.0,
    ) {
        $this->facteurTemps = max(0.01, $facteurTemps);
    }

    public function getFacteurTemps(): float
    {
        return $this->facteurTemps;
    }

    public function vitessePourHero(Hero $hero): float
    {
        $vitesse = self::VITESSE_BASE + self::COEF_MOBILITE * max(0, min(100, $hero->getMobilite()));
        $vitesse *= 1 - self::REDUCTION_FATIGUE_MAX * max(0, min(100, $hero->getFatigue())) / 100;

        return round($vitesse, 1);
    }

    /** Durée réelle du trajet, en secondes (facteur d'accélération appliqué). */
    public function dureeSecondes(float $distanceKm, float $vitesseKmH): int
    {
        if ($vitesseKmH <= 0) {
            throw new \InvalidArgumentException('La vitesse doit être strictement positive.');
        }

        return (int) ceil(max(0.0, $distanceKm) / $vitesseKmH * 3600 / $this->facteurTemps);
    }

    public function dateArrivee(\DateTimeImmutable $depart, float $distanceKm, float $vitesseKmH): \DateTimeImmutable
    {
        return $depart->modify(sprintf('+%d seconds', $this->dureeSecondes($distanceKm, $vitesseKmH)));
    }

    /** Fraction du trajet effectuée à l'instant $now (bornée à [0, 1]). */
    public function fraction(\DateTimeImmutable $depart, \DateTimeImmutable $arrivee, \DateTimeImmutable $now): float
    {
        $total = $arrivee->getTimestamp() - $depart->getTimestamp();
        if ($total <= 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, ($now->getTimestamp() - $depart->getTimestamp()) / $total));
    }

    public function secondesRestantes(\DateTimeImmutable $arrivee, \DateTimeImmutable $now): int
    {
        return max(0, $arrivee->getTimestamp() - $now->getTimestamp());
    }
}
