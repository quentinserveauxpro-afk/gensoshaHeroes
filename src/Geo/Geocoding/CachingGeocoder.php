<?php

namespace App\Geo\Geocoding;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Cache des résultats de géocodage (pool cache.app).
 *
 * - adresse trouvée : 30 jours ;
 * - adresse introuvable : 1 jour (évite de solliciter le fournisseur en boucle).
 * La clé est une empreinte SHA-256 de l'adresse normalisée : aucune adresse en clair dans le cache.
 */
#[AsDecorator(GeocoderInterface::class)]
final class CachingGeocoder implements GeocoderInterface
{
    public const TTL_TROUVE = 30 * 86400;
    public const TTL_INTROUVABLE = 86400;

    public function __construct(
        #[AutowireDecorated]
        private readonly GeocoderInterface $interne,
        private readonly CacheInterface $cache,
    ) {
    }

    public function geocoder(AdressePostale $adresse): ?ResultatGeocodage
    {
        $donnees = $this->cache->get('geocodage_'.$adresse->cleNormalisee(), function (ItemInterface $item) use ($adresse): array {
            $resultat = $this->interne->geocoder($adresse);
            $item->expiresAfter(null === $resultat ? self::TTL_INTROUVABLE : self::TTL_TROUVE);

            return null === $resultat ? ['trouve' => false] : ['trouve' => true] + $resultat->toArray();
        });

        return true === ($donnees['trouve'] ?? false) ? ResultatGeocodage::fromArray($donnees) : null;
    }
}
