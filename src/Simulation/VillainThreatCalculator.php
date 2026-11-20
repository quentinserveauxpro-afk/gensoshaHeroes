<?php

namespace App\Simulation;

use App\Entity\Mission;
use App\Enum\Difficulte;
use App\Enum\Statistique;

/**
 * Niveau de menace d'une mission, sur 0-100.
 *
 * Menace initiale :
 *   avec vilains : Σ [ 6 × niveauMenace + 0,3 × max(0, combat_v − P_combat)
 *                                       + 0,2 × max(0, intelligence_v − P_intelligence) ]
 *   sans vilain  : 10 × poids de l'urgence (Faible 10 … Critique 40)
 *   + 4 × rang de difficulté (D 4 … S 20), le tout borné à [0, 100].
 * Menace courante : décroît avec la progression des objectifs obligatoires :
 *   menace × (1 − 0,8 × progression / 100).
 */
final class VillainThreatCalculator
{
    public const POIDS_NIVEAU_MENACE = 6.0;
    public const REDUCTION_MAX = 0.8;

    /** @param array<string, int> $puissance puissance de l'équipe (TeamPowerCalculator) */
    public function menaceInitiale(Mission $mission, array $puissance): float
    {
        $menace = 0.0;
        $villains = $mission->getVillains();
        if ($villains->count() > 0) {
            foreach ($villains as $villain) {
                $menace += self::POIDS_NIVEAU_MENACE * $villain->getNiveauMenace()
                    + 0.3 * max(0, $villain->getCombat() - ($puissance[Statistique::COMBAT->value] ?? 0))
                    + 0.2 * max(0, $villain->getIntelligence() - ($puissance[Statistique::INTELLIGENCE->value] ?? 0));
            }
        } else {
            $menace = 10.0 * $mission->getNiveauUrgence()->poids();
        }
        $menace += 4.0 * ($mission->getDifficulte() ?? Difficulte::C)->rang();

        return round(max(0.0, min(100.0, $menace)), 2);
    }

    public function menaceCourante(float $menaceInitiale, float $progressionObligatoire): float
    {
        $p = max(0.0, min(100.0, $progressionObligatoire));

        return round(max(0.0, $menaceInitiale * (1 - self::REDUCTION_MAX * $p / 100)), 2);
    }

    public function libelle(float $menace): string
    {
        return match (true) {
            $menace >= 70 => 'Extrême',
            $menace >= 45 => 'Élevée',
            $menace >= 20 => 'Modérée',
            default => 'Faible',
        };
    }
}
