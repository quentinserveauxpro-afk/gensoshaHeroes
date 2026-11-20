<?php

namespace App\Geo;

use App\Infirmerie\QgConfiguration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Paramètres de la carte réelle (Leaflet + OpenStreetMap).
 *
 * Fond de carte configurable (MAP_TILE_URL) : les tuiles tile.openstreetmap.org conviennent
 * à un usage modéré avec attribution visible ; en production à fort trafic, utiliser un
 * fournisseur de tuiles dédié (politique d'usage OSMF).
 */
final readonly class CarteConfiguration
{
    /** Centre de la France métropolitaine. */
    public const CENTRE = ['lat' => 46.603354, 'lng' => 1.888334];
    public const ZOOM_INITIAL = 6;

    /** Valeur de repli si QgConfiguration (QG_LATITUDE, QG_LONGITUDE, QG_NOM) n'est pas injectée. */
    public const QG = ['lat' => 48.8566, 'lng' => 2.3522, 'label' => 'QG Genosha — Paris'];

    /** Raccourcis d'affichage vers les départements et régions d'outre-mer. */
    public const OUTRE_MER = [
        'Guadeloupe' => ['lat' => 16.265, 'lng' => -61.551, 'zoom' => 9],
        'Martinique' => ['lat' => 14.641, 'lng' => -61.024, 'zoom' => 10],
        'Guyane' => ['lat' => 3.934, 'lng' => -53.126, 'zoom' => 7],
        'La Réunion' => ['lat' => -21.115, 'lng' => 55.536, 'zoom' => 10],
        'Mayotte' => ['lat' => -12.828, 'lng' => 45.166, 'zoom' => 11],
    ];

    public function __construct(
        #[Autowire('%env(MAP_TILE_URL)%')]
        public string $urlTuiles = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        #[Autowire('%env(MAP_TILE_ATTRIBUTION)%')]
        public string $attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">Contributeurs OpenStreetMap</a>',
        public ?QgConfiguration $qgConfiguration = null,
    ) {
    }

    public static function qg(): Coordonnees
    {
        return new Coordonnees(self::QG['lat'], self::QG['lng']);
    }

    /** @return array<string, mixed> */
    public function versJs(): array
    {
        return [
            'centre' => self::CENTRE,
            'zoom' => self::ZOOM_INITIAL,
            'tuiles' => $this->urlTuiles,
            'attribution' => $this->attribution,
            'qg' => $this->qgConfiguration?->versTableau() ?? self::QG,
            'outreMer' => self::OUTRE_MER,
        ];
    }
}
