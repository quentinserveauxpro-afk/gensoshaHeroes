<?php

namespace App\Service\Mission;

use App\Entity\Hero;
use App\Entity\HeroBonus;
use App\Enum\Statistique;

/**
 * Calcule les bonus en vigueur d'un héros par statistique.
 *
 * Règle de cumul : les bonus « cumulables » s'additionnent ; parmi les bonus non cumulables
 * portant sur la même statistique, seul le plus élevé s'applique (et s'ajoute aux cumulables).
 */
final class EffectiveStatsResolver
{
    /** @return array<string, int> bonus par statistique (y compris GLOBAL) */
    public function bonusParStatistique(Hero $hero, \DateTimeImmutable $now): array
    {
        $cumulables = [];
        $nonCumulables = [];
        foreach ($hero->getBonusEnVigueur($now) as $heroBonus) {
            /** @var HeroBonus $heroBonus */
            $stat = $heroBonus->getBonus()?->getStatistique()->value;
            if (null === $stat) {
                continue;
            }
            if ($heroBonus->isCumulable()) {
                $cumulables[$stat] = ($cumulables[$stat] ?? 0) + $heroBonus->getValeur();
            } else {
                $nonCumulables[$stat] = max($nonCumulables[$stat] ?? \PHP_INT_MIN, $heroBonus->getValeur());
            }
        }

        $resultat = [];
        foreach (Statistique::cases() as $stat) {
            $resultat[$stat->value] = ($cumulables[$stat->value] ?? 0) + ($nonCumulables[$stat->value] ?? 0);
        }

        return $resultat;
    }

    /** @return array<string, int> statistiques effectives bornées à [0, 100] (hors GLOBAL) */
    public function statsEffectives(Hero $hero, \DateTimeImmutable $now): array
    {
        $bonus = $this->bonusParStatistique($hero, $now);
        $stats = [];
        foreach ($hero->getStats() as $stat => $valeur) {
            $stats[$stat] = max(Hero::STAT_MIN, min(Hero::STAT_MAX, $valeur + $bonus[$stat]));
        }

        return $stats;
    }
}
