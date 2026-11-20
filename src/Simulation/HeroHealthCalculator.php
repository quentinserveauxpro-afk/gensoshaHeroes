<?php

namespace App\Simulation;

use App\Entity\Hero;
use App\Enum\Difficulte;

/**
 * Dommages subis par un héros présent sur une mission, à chaque étape de simulation.
 *
 *   dégâts = menace / 24 × (1,2 − 0,7 × vigueur / 100) × (1 − 0,3 × combat / 100)
 *            × (0,85 + 0,05 × rang de difficulté) × (0,75 + 0,5 × aléa)
 *   arrondi à l'entier, jamais négatif ; aucun dégât si la menace est nulle.
 *
 * Les points de vie restent toujours dans [0, PV max]. À 0 PV, le héros est hors de combat
 * et ne contribue plus. Sous SEUIL_CRITIQUE % de santé, une alerte doit être émise.
 */
final class HeroHealthCalculator
{
    public const SEUIL_CRITIQUE = 25;

    /** @param array<string, int> $statsEffectives */
    public function degats(array $statsEffectives, float $menace, ?Difficulte $difficulte, float $alea): int
    {
        if ($menace <= 0) {
            return 0;
        }
        $vigueur = max(0, min(100, $statsEffectives['VIGUEUR'] ?? 0));
        $combat = max(0, min(100, $statsEffectives['COMBAT'] ?? 0));
        $alea = max(0.0, min(1.0, $alea));

        $degats = $menace / 24
            * (1.2 - 0.7 * $vigueur / 100)
            * (1 - 0.3 * $combat / 100)
            * (0.85 + 0.05 * ($difficulte ?? Difficulte::C)->rang())
            * (0.75 + 0.5 * $alea);

        return max(0, (int) round($degats));
    }

    /** Applique des dégâts (ou des soins si négatif) en bornant les PV. Retourne les PV obtenus. */
    public function appliquer(Hero $hero, int $degats): int
    {
        $pv = self::borner((int) $hero->getPointsDeVie() - $degats, $hero->getPointsDeVieMaximum());
        $hero->setPointsDeVie($pv);

        return $pv;
    }

    public static function borner(int $pv, int $max): int
    {
        return max(0, min(max(0, $max), $pv));
    }

    public function peutContribuer(Hero $hero): bool
    {
        return (int) $hero->getPointsDeVie() > 0;
    }

    public function estCritique(Hero $hero): bool
    {
        return $hero->getSantePourcentage() <= self::SEUIL_CRITIQUE;
    }

    /** Libellé textuel accessible de l'état de santé. */
    public function libelle(Hero $hero): string
    {
        $s = $hero->getSantePourcentage();

        return match (true) {
            $s <= 0 => 'Hors de combat',
            $s <= self::SEUIL_CRITIQUE => 'Critique',
            $s < 50 => 'Blessé',
            $s < 80 => 'Touché',
            default => 'Opérationnel',
        };
    }
}
