<?php

namespace App\Geo;

/**
 * Coordonnées GPS (WGS 84) en degrés décimaux.
 *
 * Les colonnes Doctrine DECIMAL(10, 7) sont hydratées en chaînes par DBAL 4 :
 * la conversion en float se fait ici, au seul endroit où l'on calcule.
 */
final readonly class Coordonnees
{
    public const PRECISION = 7;

    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if (!is_finite($latitude) || $latitude < -90.0 || $latitude > 90.0) {
            throw new \InvalidArgumentException(sprintf('Latitude invalide : %s.', $latitude));
        }
        if (!is_finite($longitude) || $longitude < -180.0 || $longitude > 180.0) {
            throw new \InvalidArgumentException(sprintf('Longitude invalide : %s.', $longitude));
        }
    }

    public static function depuisDecimaux(string|float|int|null $latitude, string|float|int|null $longitude): ?self
    {
        if (null === $latitude || null === $longitude || '' === $latitude || '' === $longitude) {
            return null;
        }

        return new self((float) $latitude, (float) $longitude);
    }

    /** Représentation DECIMAL(10, 7) attendue par Doctrine. */
    public static function versDecimal(float $valeur): string
    {
        return number_format($valeur, self::PRECISION, '.', '');
    }

    public function latitudeDecimale(): string
    {
        return self::versDecimal($this->latitude);
    }

    public function longitudeDecimale(): string
    {
        return self::versDecimal($this->longitude);
    }

    public function egale(self $autre, float $tolerance = 1e-7): bool
    {
        return abs($this->latitude - $autre->latitude) <= $tolerance && abs($this->longitude - $autre->longitude) <= $tolerance;
    }

    /** @return array{lat: float, lng: float} */
    public function toArray(): array
    {
        return ['lat' => round($this->latitude, self::PRECISION), 'lng' => round($this->longitude, self::PRECISION)];
    }
}
