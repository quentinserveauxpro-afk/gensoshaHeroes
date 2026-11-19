<?php

namespace App\Service\Map;

use App\Entity\Hero;
use App\Entity\Mission;

/**
 * Produit les données de la carte (marqueurs + zones de menace) indépendamment du rendu.
 * Le rendu actuel est un SVG abstrait ; un rendu Leaflet/OSM pourra consommer exactement
 * la même structure (via toArray() sérialisé en JSON) en projetant x/y vers lat/lng.
 */
final class TacticalMapProvider
{
    public function __construct(private readonly PositionDistanceCalculator $distances)
    {
    }

    /**
     * @param list<Mission> $missions
     * @param list<Hero>    $heros
     *
     * @return array{
     *     largeur: float, hauteur: float, base: array{x: float, y: float},
     *     missions: list<array<string, mixed>>, heros: list<array<string, mixed>>, zones: list<array<string, mixed>>
     * }
     */
    public function construire(array $missions, array $heros, ?Mission $selection = null): array
    {
        $marqueursMissions = [];
        $zones = [];
        foreach ($missions as $mission) {
            $menace = $this->niveauMenace($mission);
            $marqueursMissions[] = [
                'id' => $mission->getId(),
                'label' => $mission->getNom(),
                'x' => $mission->getPositionX(),
                'y' => $mission->getPositionY(),
                'statut' => $mission->getStatut()->value,
                'urgence' => $mission->getNiveauUrgence()->value,
                'selectionnee' => $selection === $mission,
            ];
            $zones[] = [
                'missionId' => $mission->getId(),
                'x' => $mission->getPositionX(),
                'y' => $mission->getPositionY(),
                'rayon' => 4 + 1.2 * $menace,
                'niveau' => $menace,
                'tone' => match (true) {
                    $menace >= 7 => 'danger',
                    $menace >= 4 => 'warn',
                    default => 'ok',
                },
            ];
        }

        $marqueursHeros = [];
        foreach ($heros as $hero) {
            $affectation = $hero->getAffectationActive();
            $marqueursHeros[] = [
                'id' => $hero->getId(),
                'label' => $hero->getNom(),
                'initiales' => $hero->getInitiales(),
                'x' => (float) $hero->getPositionX(),
                'y' => (float) $hero->getPositionY(),
                'statut' => $hero->getStatut()->value,
                'tone' => $hero->getStatut()->tone(),
                'missionId' => $affectation?->getMission()?->getId(),
                'distanceSelection' => null !== $selection ? round($this->distances->heroMission($hero, $selection), 1) : null,
            ];
        }

        return [
            'largeur' => TacticalGrid::LARGEUR,
            'hauteur' => TacticalGrid::HAUTEUR,
            'base' => ['x' => TacticalGrid::BASE_X, 'y' => TacticalGrid::BASE_Y],
            'missions' => $marqueursMissions,
            'heros' => $marqueursHeros,
            'zones' => $zones,
        ];
    }

    /** Menace 0-10 : vilains associés, sinon déduite de l'urgence. */
    public function niveauMenace(Mission $mission): int
    {
        $max = $mission->getNiveauMenaceMax();

        return $max > 0 ? $max : 2 * $mission->getNiveauUrgence()->poids() - 1;
    }
}
