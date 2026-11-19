<?php

namespace App\Service\Mission;

use App\Entity\Hero;
use App\Entity\Mission;
use App\Enum\Difficulte;
use App\Enum\Statistique;
use App\Service\Map\PositionDistanceCalculator;
use App\Service\Map\TacticalGrid;
use Psr\Clock\ClockInterface;

/**
 * Estime le taux de réussite d'une mission (0-100) pour une équipe donnée.
 *
 * Formule (n = taille de l'équipe, toutes les valeurs intermédiaires sur 0-100) :
 *
 *  1. Statistiques effectives par héros :
 *       s_eff = borne(s + bonus en vigueur sur s, 0, 100)                         (EffectiveStatsResolver)
 *  2. Puissance d'équipe par statistique :
 *       P_s = borne(0,6 × max(s_eff) + 0,4 × moyenne(s_eff) + synergies actives sur s, 0, 100)
 *  3. Score statistique S :
 *       si la mission a des exigences R_s (colonnes *Requis) : S = 100 × moyenne_s( min(1, P_s / R_s) )
 *       sinon                                              : S = moyenne des P_s
 *  4. Score de niveau N = 100 × min(1, moyenne(niveau) / niveauRecommandé(difficulté))
 *       (D=10, C=20, B=30, A=40, S=50)
 *  5. Score de santé H = 100 × moyenne(PV / PVmax)
 *  6. Score de proximité D = 100 × moyenne( max(0, 1 − distance / 50 km) )
 *  7. Base B = 0,45 × S + 0,15 × N + 0,20 × H + 0,20 × D
 *  8. Ajusté A = B × multiplicateur(difficulté) − menace + bonus global
 *       multiplicateur : D=1,10  C=1,00  B=0,95  A=0,90  S=0,85
 *       menace = min(40, Σ_vilains [ niveauMenace + 0,3 × max(0, combat_v − P_combat)
 *                                                  + 0,2 × max(0, intelligence_v − P_intelligence) ])
 *       bonus global = moyenne par héros des bonus GLOBAL + Σ synergies GLOBAL
 *  9. Aptitudes requises non couvertes : −10 points chacune.
 * 10. Taille d'équipe : n < min ⇒ A × n / min ; n > max ⇒ A − 5 × (n − max).
 * 11. Résultat = borne(arrondi(A), 0, 100). Équipe vide ⇒ 0.
 */
final class MissionSuccessCalculator
{
    public const POIDS_STATS = 0.45;
    public const POIDS_NIVEAU = 0.15;
    public const POIDS_SANTE = 0.20;
    public const POIDS_PROXIMITE = 0.20;
    public const MENACE_MAX = 40.0;
    public const PENALITE_APTITUDE = 10.0;
    public const PENALITE_SURNOMBRE = 5.0;

    public function __construct(
        private readonly PositionDistanceCalculator $distances,
        private readonly EffectiveStatsResolver $statsResolver,
        private readonly SynergyResolver $synergyResolver,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @param list<Hero> $equipe */
    public function taux(Mission $mission, array $equipe): int
    {
        return $this->calculer($mission, $equipe)->taux;
    }

    /** @param list<Hero> $equipe */
    public function calculer(Mission $mission, array $equipe): SuccessPrognosis
    {
        $equipe = array_values($equipe);
        $n = \count($equipe);
        $synergies = $this->synergyResolver->synergiesActives($equipe, $mission);
        $aptitudesManquantes = $this->aptitudesManquantes($mission, $equipe);

        if (0 === $n) {
            return new SuccessPrognosis(0, 0, 0, 0, 0, 0, 0, array_fill_keys(array_map(
                static fn (Statistique $s) => $s->value, Statistique::heroStats()), 0),
                $this->statsInsuffisantes($mission, []), [], 0, $aptitudesManquantes, 0);
        }

        $now = $this->clock->now();

        // 1-2. Puissance d'équipe
        $statsParHero = [];
        $bonusGlobalHeros = 0;
        foreach ($equipe as $hero) {
            $statsParHero[] = $this->statsResolver->statsEffectives($hero, $now);
            $bonusGlobalHeros += $this->statsResolver->bonusParStatistique($hero, $now)[Statistique::GLOBAL->value];
        }
        $bonusSynergie = [];
        $bonusSynergieTotal = 0;
        foreach ($synergies as $synergie) {
            $cle = $synergie->getStatistique()->value;
            $bonusSynergie[$cle] = ($bonusSynergie[$cle] ?? 0) + $synergie->getValeurBonus();
            $bonusSynergieTotal += $synergie->getValeurBonus();
        }

        $puissance = [];
        $maxParStat = [];
        foreach (Statistique::heroStats() as $stat) {
            $valeurs = array_column($statsParHero, $stat->value);
            $maxParStat[$stat->value] = max($valeurs);
            $p = 0.6 * max($valeurs) + 0.4 * (array_sum($valeurs) / $n) + ($bonusSynergie[$stat->value] ?? 0);
            $puissance[$stat->value] = (int) round(self::borne($p));
        }

        // 3. Score statistique
        $requises = $mission->getStatsRequises();
        if ([] !== $requises) {
            $couvertures = [];
            foreach ($requises as $stat => $requis) {
                $couvertures[] = min(1.0, $puissance[$stat] / $requis);
            }
            $scoreStats = 100 * array_sum($couvertures) / \count($couvertures);
        } else {
            $scoreStats = array_sum($puissance) / \count($puissance);
        }

        // 4. Niveau
        $difficulte = $mission->getDifficulte() ?? Difficulte::C;
        $niveauMoyen = array_sum(array_map(static fn (Hero $h) => (int) $h->getNiveau(), $equipe)) / $n;
        $scoreNiveau = 100 * min(1.0, $niveauMoyen / $difficulte->niveauRecommande());

        // 5. Santé
        $scoreSante = array_sum(array_map(static fn (Hero $h) => $h->getSantePourcentage(), $equipe)) / $n;

        // 6. Proximité
        $proximites = array_map(
            fn (Hero $h) => max(0.0, 1 - $this->distances->heroMission($h, $mission) / TacticalGrid::DISTANCE_MAX_UTILE),
            $equipe,
        );
        $scoreProximite = 100 * array_sum($proximites) / $n;

        // 7. Base
        $base = self::POIDS_STATS * $scoreStats
            + self::POIDS_NIVEAU * $scoreNiveau
            + self::POIDS_SANTE * $scoreSante
            + self::POIDS_PROXIMITE * $scoreProximite;

        // 8. Difficulté, menace et bonus global
        $menace = 0.0;
        foreach ($mission->getVillains() as $villain) {
            $menace += $villain->getNiveauMenace()
                + 0.3 * max(0, $villain->getCombat() - $puissance[Statistique::COMBAT->value])
                + 0.2 * max(0, $villain->getIntelligence() - $puissance[Statistique::INTELLIGENCE->value]);
        }
        $menace = min(self::MENACE_MAX, $menace);
        $bonusGlobal = $bonusGlobalHeros / $n + ($bonusSynergie[Statistique::GLOBAL->value] ?? 0);

        $ajuste = $base * $difficulte->multiplicateur() - $menace + $bonusGlobal;

        // 9. Aptitudes
        $ajuste -= self::PENALITE_APTITUDE * \count($aptitudesManquantes);

        // 10. Taille d'équipe
        $min = $mission->getTailleEquipeMinimale();
        $max = $mission->getTailleEquipeMaximale();
        if ($n < $min) {
            $ajuste *= $n / $min;
        } elseif ($n > $max) {
            $ajuste -= self::PENALITE_SURNOMBRE * ($n - $max);
        }

        return new SuccessPrognosis(
            (int) round(self::borne($ajuste)),
            round($scoreStats, 1),
            round($scoreNiveau, 1),
            round($scoreSante, 1),
            round($scoreProximite, 1),
            round($menace, 1),
            round($bonusGlobal, 1),
            $puissance,
            $this->statsInsuffisantes($mission, $maxParStat),
            $synergies,
            $bonusSynergieTotal,
            $aptitudesManquantes,
            $n,
        );
    }

    /**
     * @param list<Hero> $equipe
     *
     * @return list<\App\Entity\Aptitude>
     */
    public function aptitudesManquantes(Mission $mission, array $equipe): array
    {
        $manquantes = [];
        foreach ($mission->getAptitudesRequises() as $aptitude) {
            $couverte = false;
            foreach ($equipe as $hero) {
                if ($hero->getAptitudes()->contains($aptitude)) {
                    $couverte = true;
                    break;
                }
            }
            if (!$couverte) {
                $manquantes[] = $aptitude;
            }
        }

        return $manquantes;
    }

    /**
     * Exigence satisfaite si au moins un héros atteint la valeur requise (statistique effective).
     *
     * @param array<string, int> $maxParStat
     *
     * @return array<string, array{requis: int, actuel: int}>
     */
    private function statsInsuffisantes(Mission $mission, array $maxParStat): array
    {
        $insuffisantes = [];
        foreach ($mission->getStatsRequises() as $stat => $requis) {
            $actuel = $maxParStat[$stat] ?? 0;
            if ($actuel < $requis) {
                $insuffisantes[$stat] = ['requis' => $requis, 'actuel' => $actuel];
            }
        }

        return $insuffisantes;
    }

    private static function borne(float $valeur): float
    {
        return max(0.0, min(100.0, $valeur));
    }
}
