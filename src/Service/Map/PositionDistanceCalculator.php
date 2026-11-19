<?php

namespace App\Service\Map;

use App\Entity\Hero;
use App\Entity\Mission;

/**
 * Seul point de calcul des distances de l'application.
 *
 * Carte abstraite : distance euclidienne sqrt((x2 − x1)² + (y2 − y1)²), en km
 * (1 unité de grille = 1 km, voir TacticalGrid).
 */
final class PositionDistanceCalculator
{
    public function distance(float $x1, float $y1, float $x2, float $y2): float
    {
        return sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);
    }

    public function heroMission(Hero $hero, Mission $mission): float
    {
        return $this->distance(
            (float) $hero->getPositionX(),
            (float) $hero->getPositionY(),
            $mission->getPositionX(),
            $mission->getPositionY(),
        );
    }

    public function heroBase(Hero $hero): float
    {
        return $this->distance(
            (float) $hero->getPositionX(),
            (float) $hero->getPositionY(),
            TacticalGrid::BASE_X,
            TacticalGrid::BASE_Y,
        );
    }

    public function isALaBase(Hero $hero): bool
    {
        return $this->heroBase($hero) <= TacticalGrid::RAYON_BASE;
    }

    /** Distance arrondie à 0,1 km, pour l'affichage. */
    public function heroMissionArrondie(Hero $hero, Mission $mission): float
    {
        return round($this->heroMission($hero, $mission), 1);
    }
}
