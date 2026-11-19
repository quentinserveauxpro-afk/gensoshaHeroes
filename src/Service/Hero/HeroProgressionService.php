<?php

namespace App\Service\Hero;

use App\Entity\Hero;
use App\Enum\Statistique;

/**
 * Progression des héros.
 *
 * - Hero::$xp contient l'expérience accumulée dans le niveau courant.
 * - Seuil pour passer du niveau n au niveau n+1 : 375 × n XP.
 * - À chaque niveau gagné : +2 sur chaque statistique, +5 PV maximum (statistiques bornées à 100).
 * - Le niveau maximal est 100 ; l'XP y est plafonnée au seuil.
 */
final class HeroProgressionService
{
    public const NIVEAU_MAX = 100;
    public const XP_PAR_NIVEAU = 375;
    public const GAIN_STAT_PAR_NIVEAU = 2;
    public const GAIN_PV_PAR_NIVEAU = 5;

    public function seuil(int $niveau): int
    {
        return self::XP_PAR_NIVEAU * max(1, $niveau);
    }

    public function ajouterExperience(Hero $hero, int $experience): ProgressionResult
    {
        if ($experience < 0) {
            throw new \InvalidArgumentException('L\'expérience ajoutée doit être positive.');
        }
        $niveauAvant = max(1, (int) $hero->getNiveau());
        $niveau = $niveauAvant;
        $xp = max(0, (int) $hero->getXp()) + $experience;

        while ($niveau < self::NIVEAU_MAX && $xp >= $this->seuil($niveau)) {
            $xp -= $this->seuil($niveau);
            ++$niveau;
        }
        if (self::NIVEAU_MAX === $niveau) {
            $xp = min($xp, $this->seuil($niveau));
        }

        $hero->setNiveau($niveau);
        $hero->setXp($xp);

        $gagnes = $niveau - $niveauAvant;
        if ($gagnes > 0) {
            $this->ameliorerStatistiques($hero, $gagnes * self::GAIN_STAT_PAR_NIVEAU);
            $hero->setPointsDeVieMaximum($hero->getPointsDeVieMaximum() + $gagnes * self::GAIN_PV_PAR_NIVEAU);
        }

        return new ProgressionResult($niveauAvant, $niveau, $experience);
    }

    /** Ajoute $points à chaque statistique (ou à une seule), en respectant le maximum autorisé. */
    public function ameliorerStatistiques(Hero $hero, int $points, ?Statistique $seulement = null): void
    {
        $cibles = null !== $seulement ? [$seulement] : Statistique::heroStats();
        foreach ($cibles as $stat) {
            $hero->setStat($stat, $this->borner($hero->getStat($stat) + $points));
        }
    }

    public function borner(int $valeur): int
    {
        return max(Hero::STAT_MIN, min(Hero::STAT_MAX, $valeur));
    }

    /** Progression vers le niveau suivant, en pourcentage. */
    public function pourcentageNiveau(Hero $hero): int
    {
        $seuil = $this->seuil((int) $hero->getNiveau());

        return (int) min(100, round(100 * (int) $hero->getXp() / $seuil));
    }
}
