<?php

namespace App\Simulation;

use App\Entity\Hero;
use App\Entity\Mission;
use App\Enum\Statistique;
use App\Service\Mission\EffectiveStatsResolver;
use App\Service\Mission\SynergyResolver;

/**
 * Puissance d'une équipe par statistique, sur 0-100 — même règle que MissionSuccessCalculator :
 *   P_s = borne(0,6 × max(s_eff) + 0,4 × moyenne(s_eff) + synergies actives sur s, 0, 100)
 * où s_eff intègre les bonus actifs (EffectiveStatsResolver).
 * Un bonus de niveau s'ajoute : +0,1 point par niveau au-dessus du niveau recommandé (max +5).
 */
final class TeamPowerCalculator
{
    public function __construct(
        private readonly EffectiveStatsResolver $statsResolver,
        private readonly SynergyResolver $synergyResolver,
    ) {
    }

    /**
     * @param list<Hero> $equipe héros en état de contribuer
     *
     * @return array<string, int> clé = valeur de Statistique (hors GLOBAL)
     */
    public function puissance(array $equipe, Mission $mission, \DateTimeImmutable $now): array
    {
        $resultat = array_fill_keys(array_map(static fn (Statistique $s) => $s->value, Statistique::heroStats()), 0);
        $n = \count($equipe);
        if (0 === $n) {
            return $resultat;
        }

        $statsParHero = array_map(fn (Hero $h) => $this->statsResolver->statsEffectives($h, $now), $equipe);
        $synergies = [];
        foreach ($this->synergyResolver->synergiesActives($equipe, $mission) as $synergie) {
            $cle = $synergie->getStatistique()->value;
            $synergies[$cle] = ($synergies[$cle] ?? 0) + $synergie->getValeurBonus();
        }
        $globalSynergie = $synergies[Statistique::GLOBAL->value] ?? 0;

        $recommande = $mission->getDifficulte()?->niveauRecommande() ?? 20;
        $niveauMoyen = array_sum(array_map(static fn (Hero $h) => (int) $h->getNiveau(), $equipe)) / $n;
        $bonusNiveau = min(5.0, max(0.0, ($niveauMoyen - $recommande) * 0.1));

        foreach (Statistique::heroStats() as $stat) {
            $valeurs = array_column($statsParHero, $stat->value);
            $p = 0.6 * max($valeurs) + 0.4 * array_sum($valeurs) / $n
                + ($synergies[$stat->value] ?? 0) + $globalSynergie / 5 + $bonusNiveau;
            $resultat[$stat->value] = (int) round(max(0.0, min(100.0, $p)));
        }

        return $resultat;
    }
}
