<?php

namespace App\Geo;

/**
 * Calculs géodésiques sur la sphère terrestre (WGS 84 approché par une sphère).
 *
 * - distanceKm() : formule de Haversine (jamais de distance euclidienne sur des degrés).
 * - pointIntermediaire() : interpolation le long de l'arc de grand cercle (slerp),
 *   cohérente avec Haversine : à la fraction f, la distance parcourue vaut f × distance totale.
 *
 * Erreur de l'approximation sphérique : < 0,5 % par rapport à l'ellipsoïde, suffisant ici.
 */
final class DistanceCalculator
{
    /** Rayon moyen de la Terre (IUGG), en km. */
    public const RAYON_TERRE_KM = 6371.0088;

    public function distanceKm(Coordonnees $a, Coordonnees $b): float
    {
        $phi1 = deg2rad($a->latitude);
        $phi2 = deg2rad($b->latitude);
        $dPhi = $phi2 - $phi1;
        $dLambda = deg2rad($b->longitude - $a->longitude);

        $h = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;

        return 2 * self::RAYON_TERRE_KM * asin(min(1.0, sqrt($h)));
    }

    /** Distance arrondie à 0,1 km, pour l'affichage. */
    public function distanceArrondie(Coordonnees $a, Coordonnees $b): float
    {
        return round($this->distanceKm($a, $b), 1);
    }

    /**
     * Point situé à la fraction $fraction (bornée à [0, 1]) du trajet a → b sur le grand cercle.
     */
    public function pointIntermediaire(Coordonnees $a, Coordonnees $b, float $fraction): Coordonnees
    {
        $f = max(0.0, min(1.0, $fraction));
        if (0.0 === $f) {
            return $a;
        }
        if (1.0 === $f) {
            return $b;
        }

        $phi1 = deg2rad($a->latitude);
        $lambda1 = deg2rad($a->longitude);
        $phi2 = deg2rad($b->latitude);
        $lambda2 = deg2rad($b->longitude);
        $delta = $this->distanceKm($a, $b) / self::RAYON_TERRE_KM; // distance angulaire

        if ($delta < 1e-12) {
            return $a;
        }

        $k1 = sin((1 - $f) * $delta) / sin($delta);
        $k2 = sin($f * $delta) / sin($delta);

        $x = $k1 * cos($phi1) * cos($lambda1) + $k2 * cos($phi2) * cos($lambda2);
        $y = $k1 * cos($phi1) * sin($lambda1) + $k2 * cos($phi2) * sin($lambda2);
        $z = $k1 * sin($phi1) + $k2 * sin($phi2);

        $phi = atan2($z, sqrt($x * $x + $y * $y));
        $lambda = atan2($y, $x);

        return new Coordonnees(rad2deg($phi), self::normaliserLongitude(rad2deg($lambda)));
    }

    private static function normaliserLongitude(float $longitude): float
    {
        $l = fmod($longitude + 540.0, 360.0) - 180.0;

        return -180.0 === $l ? 180.0 : $l;
    }
}
