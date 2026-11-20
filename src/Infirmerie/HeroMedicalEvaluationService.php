<?php

namespace App\Infirmerie;

use App\Entity\Hero;
use App\Enum\MotifInfirmerie;

/**
 * Évaluation médicale d'un héros (calculs purs, sans écriture).
 *
 * Santé (%) = pointsDeVie / pointsDeVieMaximum × 100, bornée à [0, 100] ;
 * un maximum nul ou négatif, ou des points de vie manquants, donnent 0 %.
 *
 * Rapatriement requis (par ordre de priorité) :
 *   - 0 point de vie, ou santé ≤ seuil critique         → SANTE_CRITIQUE ;
 *   - fatigue ≥ seuil critique                          → EPUISEMENT.
 * Sortie de l'infirmerie (règle fixe, non configurable) :
 *   points de vie == points de vie maximum  ET  fatigue == 0.
 * Les deux conditions sont obligatoires.
 *
 * Le serveur est seul juge : le navigateur n'appelle jamais cette décision.
 */
final readonly class HeroMedicalEvaluationService
{
    public const REGLE_SORTIE = 'santé au maximum et fatigue à 0';
    /** Tolérance des calculs décimaux (les valeurs entières du héros sont exactes). */
    private const EPSILON = 1e-6;

    public function __construct(private InfirmerieConfiguration $config)
    {
    }

    public static function santePourcentage(int|float|null $pointsDeVie, int|float|null $maximum): float
    {
        if (null === $pointsDeVie || null === $maximum || $maximum <= 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, $pointsDeVie / $maximum * 100));
    }

    public static function bornerSante(int|float|null $pointsDeVie, int|float|null $maximum): float
    {
        return max(0.0, min(max(0.0, (float) $maximum), (float) $pointsDeVie));
    }

    public static function bornerFatigue(int|float|null $fatigue): float
    {
        return max(0.0, min(100.0, (float) $fatigue));
    }

    public function pourcentage(Hero $hero): float
    {
        return self::santePourcentage($hero->getPointsDeVie(), $hero->getPointsDeVieMaximum());
    }

    public function estSanteCritique(Hero $hero): bool
    {
        return (int) $hero->getPointsDeVie() <= 0 || $this->pourcentage($hero) <= $this->config->santeCritique;
    }

    public function estFatigueCritique(Hero $hero): bool
    {
        return self::bornerFatigue($hero->getFatigue()) >= $this->config->fatigueCritique;
    }

    /** Motif de rapatriement automatique, ou null si le héros peut continuer. */
    public function motifRapatriement(Hero $hero): ?MotifInfirmerie
    {
        return match (true) {
            $this->estSanteCritique($hero) => MotifInfirmerie::SANTE_CRITIQUE,
            $this->estFatigueCritique($hero) => MotifInfirmerie::EPUISEMENT,
            default => null,
        };
    }

    /** Points de vie à atteindre pour sortir : toujours le maximum. */
    public function santeCible(Hero $hero): int
    {
        return max(0, $hero->getPointsDeVieMaximum());
    }

    /** Fatigue à atteindre pour sortir : toujours 0. */
    public function fatigueCible(): int
    {
        return 0;
    }

    /** Santé au maximum (et seulement elle) : la fatigue peut rester à éliminer. */
    public static function santeRetablie(int|float|null $pointsDeVie, int|float|null $maximum): bool
    {
        return (float) $pointsDeVie + self::EPSILON >= max(0.0, (float) $maximum);
    }

    public static function fatigueEliminee(int|float|null $fatigue): bool
    {
        return (float) $fatigue <= self::EPSILON;
    }

    /** Règle de sortie : points de vie == maximum ET fatigue == 0. */
    public static function estRetabli(int|float|null $pointsDeVie, int|float|null $maximum, int|float|null $fatigue): bool
    {
        return self::santeRetablie($pointsDeVie, $maximum) && self::fatigueEliminee($fatigue);
    }

    public function heroRetabli(Hero $hero): bool
    {
        return self::estRetabli($hero->getPointsDeVie(), $hero->getPointsDeVieMaximum(), $hero->getFatigue());
    }
}
