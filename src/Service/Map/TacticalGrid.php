<?php

namespace App\Service\Map;

/**
 * Référentiel de la carte tactique abstraite.
 *
 * Les coordonnées (positionX, positionY) sont exprimées en kilomètres sur une grille
 * de LARGEUR × HAUTEUR km dont l'origine est le coin supérieur gauche. Pour une future
 * migration vers Leaflet/OpenStreetMap, seule la projection (toLatLng) est à implémenter :
 * les entités, la distance et le fournisseur de marqueurs restent inchangés.
 */
final class TacticalGrid
{
    public const LARGEUR = 100.0;
    public const HAUTEUR = 100.0;

    /** Position du QG Genosha. */
    public const BASE_X = 50.0;
    public const BASE_Y = 50.0;

    /** Rayon (km) dans lequel un héros est considéré « présent à la base ». */
    public const RAYON_BASE = 1.5;

    /** Rayon (km) du filtre « proximité ». */
    public const RAYON_PROXIMITE = 5.0;

    /** Distance (km) au-delà de laquelle un héros n'apporte plus de bonus de proximité. */
    public const DISTANCE_MAX_UTILE = 50.0;
}
