<?php

namespace App\GameTime;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Échelle temporelle accélérée du jeu (paramètres app.game_time.*, config/packages/game_time.yaml).
 * Seule la durée est accélérée : les distances réelles affichées restent exactes.
 */
final readonly class GameTimeConfiguration
{
    public int $trajetMin;
    public int $trajetMax;
    public int $infirmerieMin;
    public int $infirmerieMax;

    public function __construct(
        #[Autowire('%app.game_time.hero_travel_min_seconds%')]
        int $trajetMin = 30,
        #[Autowire('%app.game_time.hero_travel_max_seconds%')]
        int $trajetMax = 600,
        #[Autowire('%app.game_time.hero_travel_reference_km%')]
        public float $distanceReferenceKm = 1000.0,
        #[Autowire('%app.game_time.flight_modifier%')]
        public float $modificateurVol = 0.75,
        #[Autowire('%app.game_time.flight_aptitude%')]
        public string $aptitudeVol = 'VOL',
        #[Autowire('%app.game_time.infirmary_min_seconds%')]
        int $infirmerieMin = 60,
        #[Autowire('%app.game_time.infirmary_max_seconds%')]
        int $infirmerieMax = 900,
        #[Autowire('%app.game_time.medical_support_modifier%')]
        public float $modificateurSoutien = 0.85,
        #[Autowire('%app.game_time.saturated_capacity_modifier%')]
        public float $modificateurSaturation = 1.10,
        #[Autowire('%app.game_time.critical_severity_modifier%')]
        public float $modificateurCritique = 1.35,
        #[Autowire('%app.game_time.injury_severity_modifier%')]
        public float $modificateurBlessure = 1.10,
    ) {
        // Bornes toujours cohérentes, même avec une configuration erronée.
        $this->trajetMin = max(0, $trajetMin);
        $this->trajetMax = max($this->trajetMin, $trajetMax);
        $this->infirmerieMin = max(0, $infirmerieMin);
        $this->infirmerieMax = max($this->infirmerieMin, $infirmerieMax);
    }
}
