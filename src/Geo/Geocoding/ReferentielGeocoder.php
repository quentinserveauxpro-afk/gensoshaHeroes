<?php

namespace App\Geo\Geocoding;

use App\Geo\Coordonnees;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Géocodeur hors ligne utilisé en environnement de test : aucune requête réseau.
 * Il reconnaît uniquement une liste fermée de villes (centre-ville) et renvoie null sinon,
 * ce qui permet de tester le cas « adresse introuvable ».
 */
#[AsAlias(GeocoderInterface::class, when: 'test')]
final class ReferentielGeocoder implements GeocoderInterface
{
    public const FOURNISSEUR = 'referentiel';

    /** @var array<string, array{0: float, 1: float, 2: string}> */
    public const VILLES = [
        'paris' => [48.8566, 2.3522, '75001'],
        'lyon' => [45.7640, 4.8357, '69001'],
        'marseille' => [43.2965, 5.3698, '13001'],
        'lille' => [50.6292, 3.0573, '59000'],
        'bordeaux' => [44.8378, -0.5792, '33000'],
        'toulouse' => [43.6047, 1.4442, '31000'],
        'strasbourg' => [48.5734, 7.7521, '67000'],
        'nantes' => [47.2184, -1.5536, '44000'],
        'saint-denis' => [-20.8823, 55.4504, '97400'],
        'pointe-a-pitre' => [16.2411, -61.5331, '97110'],
    ];

    public function geocoder(AdressePostale $adresse): ?ResultatGeocodage
    {
        $ville = mb_strtolower(trim((string) $adresse->ville));
        $ville = strtr($ville, ['à' => 'a', 'é' => 'e', 'è' => 'e', ' ' => '-']);
        if (!isset(self::VILLES[$ville])) {
            return null;
        }
        [$lat, $lng, $cp] = self::VILLES[$ville];

        return new ResultatGeocodage(new Coordonnees($lat, $lng), $adresse->versRequete(), $adresse->codePostal ?? $cp, $adresse->ville, self::FOURNISSEUR);
    }
}
