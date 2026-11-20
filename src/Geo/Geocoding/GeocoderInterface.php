<?php

namespace App\Geo\Geocoding;

/**
 * Contrat de géocodage, indépendant du fournisseur (Nominatim aujourd'hui, BAN ou autre demain).
 *
 * Ne doit jamais être appelé depuis Twig ou le navigateur. Retourne null si l'adresse est
 * introuvable ou si le fournisseur est indisponible : l'appelant conserve alors l'adresse
 * saisie sans coordonnées (jamais de position aléatoire).
 */
interface GeocoderInterface
{
    public function geocoder(AdressePostale $adresse): ?ResultatGeocodage;
}
