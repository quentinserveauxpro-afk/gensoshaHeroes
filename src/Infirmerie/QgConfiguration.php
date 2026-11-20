<?php

namespace App\Infirmerie;

use App\Geo\Coordonnees;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Position du QG (destination des rapatriements, point de départ par défaut des héros sans
 * position connue). Source unique : variables QG_LATITUDE, QG_LONGITUDE et QG_NOM.
 */
final readonly class QgConfiguration
{
    /** Rayon (km) dans lequel un héros est considéré au QG. */
    public const RAYON_KM = 1.0;

    public function __construct(
        #[Autowire('%env(float:QG_LATITUDE)%')]
        public float $latitude = 48.8566,
        #[Autowire('%env(float:QG_LONGITUDE)%')]
        public float $longitude = 2.3522,
        #[Autowire('%env(QG_NOM)%')]
        public string $nom = 'QG Genosha — Paris',
    ) {
    }

    public function coordonnees(): Coordonnees
    {
        return new Coordonnees($this->latitude, $this->longitude);
    }

    /** @return array{lat: float, lng: float, label: string} */
    public function versTableau(): array
    {
        return ['lat' => $this->latitude, 'lng' => $this->longitude, 'label' => $this->nom];
    }
}
