<?php

namespace App\Geo\Geocoding;

use App\Geo\Coordonnees;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Géocodage via Nominatim (OpenStreetMap).
 *
 * Respect de la politique d'usage (https://operations.osmfoundation.org/policies/nominatim/) :
 * - au plus 1 requête par seconde (limiteur « geocodage_nominatim », attente bloquante) ;
 * - User-Agent identifiant l'application (GEOCODER_USER_AGENT) ;
 * - résultats mis en cache (CachingGeocoder) : pas de requêtes répétées ;
 * - aucun géocodage en masse, uniquement à la saisie d'une adresse.
 *
 * Les journaux ne contiennent jamais l'adresse (donnée personnelle potentielle).
 */
#[AsAlias(GeocoderInterface::class, when: ['dev', 'prod'])]
final class NominatimGeocoder implements GeocoderInterface
{
    public const FOURNISSEUR = 'nominatim';

    /** Codes pays acceptés : métropole et outre-mer. */
    private const PAYS = 'fr,gp,mq,gf,re,yt,pm,bl,mf,wf,pf,nc';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'limiter.geocodage_nominatim')]
        private readonly RateLimiterFactoryInterface $limiteur,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(GEOCODER_URL)%')]
        private readonly string $url = 'https://nominatim.openstreetmap.org',
        #[Autowire('%env(GEOCODER_USER_AGENT)%')]
        private readonly string $userAgent = 'GenoshaHeroes/1.0',
    ) {
    }

    public function geocoder(AdressePostale $adresse): ?ResultatGeocodage
    {
        $this->limiteur->create('nominatim')->reserve(1, 5)->wait();

        try {
            $reponse = $this->httpClient->request('GET', rtrim($this->url, '/').'/search', [
                'query' => [
                    'q' => $adresse->versRequete(),
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 1,
                    'countrycodes' => self::PAYS,
                ],
                'headers' => ['User-Agent' => $this->userAgent, 'Accept-Language' => 'fr'],
                'timeout' => 4,
                'max_duration' => 6,
            ]);
            $donnees = $reponse->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Géocodage indisponible ({fournisseur}) : {erreur}', ['fournisseur' => self::FOURNISSEUR, 'erreur' => $e::class]);

            return null;
        }

        $premier = $donnees[0] ?? null;
        if (!\is_array($premier) || !isset($premier['lat'], $premier['lon'])) {
            return null;
        }

        try {
            $coordonnees = new Coordonnees((float) $premier['lat'], (float) $premier['lon']);
        } catch (\InvalidArgumentException) {
            return null;
        }
        $details = \is_array($premier['address'] ?? null) ? $premier['address'] : [];

        return new ResultatGeocodage(
            $coordonnees,
            mb_substr((string) ($premier['display_name'] ?? $adresse->versRequete()), 0, 255),
            $details['postcode'] ?? $adresse->codePostal,
            $details['city'] ?? $details['town'] ?? $details['village'] ?? $details['municipality'] ?? $adresse->ville,
            self::FOURNISSEUR,
        );
    }
}
