<?php

namespace App\Enum;

/** Origine des coordonnées GPS d'une GeoPosition. */
enum SourceGeolocalisation: string
{
    /** Obtenues par le service de géocodage à partir de l'adresse saisie. */
    case GEOCODAGE = 'GEOCODAGE';
    /** Saisies ou corrigées manuellement par un opérateur. */
    case SAISIE_OPERATEUR = 'SAISIE_OPERATEUR';
    /** Position de référence (QG, base d'un héros, jeu de démonstration). */
    case REFERENCE = 'REFERENCE';
    /** Mise à jour par le moteur de déplacement (arrivée d'un héros). */
    case DEPLACEMENT = 'DEPLACEMENT';

    public function label(): string
    {
        return match ($this) {
            self::GEOCODAGE => 'Géocodage de l\'adresse',
            self::SAISIE_OPERATEUR => 'Saisie opérateur',
            self::REFERENCE => 'Position de référence',
            self::DEPLACEMENT => 'Dernière position connue',
        };
    }
}
