<?php

namespace App\Geo;

use App\Entity\GeoPosition;
use App\Enum\SourceGeolocalisation;
use App\Geo\Geocoding\AdressePostale;
use App\Geo\Geocoding\GeocoderInterface;

/**
 * Transforme une adresse saisie en GeoPosition, en conservant toujours l'adresse d'origine.
 * Aucune position n'est inventée : si l'adresse est introuvable, aucune GeoPosition n'est créée.
 */
final class GeolocalisationService
{
    public function __construct(private readonly GeocoderInterface $geocoder)
    {
    }

    public function depuisAdresse(AdressePostale $adresse, ?GeoPosition $existante = null): ?GeoPosition
    {
        $resultat = $this->geocoder->geocoder($adresse);
        if (null === $resultat) {
            return null;
        }

        $position = $existante ?? new GeoPosition();
        $position->setCoordonnees($resultat->coordonnees, SourceGeolocalisation::GEOCODAGE)
            ->setAdresseSaisie(mb_substr($adresse->versRequete(), 0, 255))
            ->setLibelle($resultat->libelle)
            ->setCodePostal($resultat->codePostal ?? $adresse->codePostal)
            ->setVille($resultat->ville ?? $adresse->ville)
            ->setFournisseur($resultat->fournisseur);

        return $position;
    }

    public function manuelle(Coordonnees $coordonnees, ?string $adresseSaisie = null, ?GeoPosition $existante = null): GeoPosition
    {
        $position = $existante ?? new GeoPosition();
        $position->setCoordonnees($coordonnees, SourceGeolocalisation::SAISIE_OPERATEUR)
            ->setAdresseSaisie(null !== $adresseSaisie ? mb_substr($adresseSaisie, 0, 255) : $position->getAdresseSaisie())
            ->setFournisseur(null);

        return $position;
    }
}
