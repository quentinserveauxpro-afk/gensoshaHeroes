<?php

namespace App\GameTime;

use App\Entity\Hero;
use App\Infirmerie\HeroMedicalEvaluationService;
use App\Service\Mission\EffectiveStatsResolver;

/**
 * Durée (en secondes réelles) d'un déplacement accéléré : vers une mission ou retour au QG.
 *
 *   ratio     = min(distance / distanceReference, 1)
 *   base      = min + ratio × (max − min)
 *   mobilité  = 1,20 − mobilité effective / 100 × 0,40   (bonus de vitesse actifs inclus)
 *   fatigue   = 1 + fatigue / 100 × 0,30
 *   santé     = 1 + (100 − santé %) / 100 × 0,20
 *   vol       = modificateurVol si le héros possède l'aptitude de vol, 1 sinon
 *   durée     = clamp(round(base × mobilité × fatigue × santé × vol), min, max)
 *
 * Un héros déjà sur place (distance ≤ seuilSurPlaceKm) arrive immédiatement (0 s).
 * Déterministe, jamais négative, jamais au-delà du maximum (10 min par défaut).
 * Le projet ne modélise pas de moyen de transport : seule l'aptitude de vol est prise en compte.
 */
final readonly class HeroTravelTimeCalculator
{
    public function __construct(
        private GameTimeConfiguration $config,
        private EffectiveStatsResolver $stats,
    ) {
    }

    public function duree(Hero $hero, float $distanceKm, \DateTimeImmutable $now, float $seuilSurPlaceKm = 0.05): int
    {
        if (!is_finite($distanceKm) || $distanceKm <= $seuilSurPlaceKm) {
            return 0;
        }
        $ratio = min(1.0, $distanceKm / max(0.001, $this->config->distanceReferenceKm));
        $base = $this->config->trajetMin + $ratio * ($this->config->trajetMax - $this->config->trajetMin);
        $duree = $base * array_product($this->modificateurs($hero, $now));

        return self::borner($duree, $this->config->trajetMin, $this->config->trajetMax);
    }

    /** @return array{mobilite: float, fatigue: float, sante: float, vol: float} */
    public function modificateurs(Hero $hero, \DateTimeImmutable $now): array
    {
        $mobilite = max(0, min(100, $this->stats->statsEffectives($hero, $now)['MOBILITE'] ?? $hero->getMobilite()));
        $fatigue = HeroMedicalEvaluationService::bornerFatigue($hero->getFatigue());
        $sante = HeroMedicalEvaluationService::santePourcentage($hero->getPointsDeVie(), $hero->getPointsDeVieMaximum());

        return [
            'mobilite' => 1.20 - $mobilite / 100 * 0.40,
            'fatigue' => 1 + $fatigue / 100 * 0.30,
            'sante' => 1 + (100 - $sante) / 100 * 0.20,
            'vol' => $this->peutVoler($hero) ? $this->config->modificateurVol : 1.0,
        ];
    }

    public function peutVoler(Hero $hero): bool
    {
        foreach ($hero->getAptitudes() as $aptitude) {
            if ($aptitude->getCode() === $this->config->aptitudeVol) {
                return true;
            }
        }

        return false;
    }

    /** Vitesse équivalente (km/h simulés), informative : distance réelle / durée accélérée. */
    public static function vitesseEquivalente(float $distanceKm, int $dureeSecondes): float
    {
        return $dureeSecondes > 0 ? round($distanceKm / $dureeSecondes * 3600, 1) : 0.0;
    }

    public static function borner(float $secondes, int $min, int $max): int
    {
        if (!is_finite($secondes)) {
            return $max;
        }

        return max($min, min($max, (int) round($secondes)));
    }
}
