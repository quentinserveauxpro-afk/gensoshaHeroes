<?php

namespace App\Twig;

use App\Geo\CarteConfiguration;
use App\Realtime\EtatTactiqueProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

/** Fonctions Twig de la carte réelle et du temps réel. */
final class CarteExtension
{
    public function __construct(
        private readonly CarteConfiguration $carte,
        private readonly EtatTactiqueProvider $etat,
        #[Autowire('%env(REALTIME_TRANSPORT)%')] private readonly string $transport,
    ) {
    }

    /** Configuration Leaflet (tuiles, attribution, centre, QG, raccourcis outre-mer). */
    #[AsTwigFunction('carte_config')]
    public function config(): array
    {
        return $this->carte->versJs();
    }

    /** Instantané initial embarqué dans la page : un rechargement reprend l'état exact du serveur. */
    #[AsTwigFunction('etat_tactique')]
    public function etat(): array
    {
        return $this->etat->instantane();
    }

    #[AsTwigFunction('temps_reel_transport')]
    public function transport(): string
    {
        return \in_array($this->transport, ['sse', 'polling'], true) ? $this->transport : 'sse';
    }
}
