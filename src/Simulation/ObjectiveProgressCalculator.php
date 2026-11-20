<?php

namespace App\Simulation;

use App\Entity\Hero;
use App\Entity\Mission;
use App\Entity\ObjectifMission;
use App\Enum\Difficulte;

/**
 * Progression d'un objectif par étape de simulation (points de pourcentage, objectif borné à 100).
 *
 *   gain = GAIN_BASE × fStat × fÉquipe × fMenace × multiplicateur(difficulté) × fAptitudes
 *
 *   fStat      : statistique clé de l'objectif → borne(P_s / exigence, 0,2, 1,5) (exigence par défaut 50) ;
 *                sinon moyenne de borne(P_s / R_s) sur les exigences de la mission ;
 *                sinon moyenne des puissances / 60, bornée de la même façon.
 *   fÉquipe    : min(1,3, √(héros présents / taille minimale))
 *   fMenace    : max(0,25, 1 − menace / 130)
 *   fAptitudes : 0,1 si une aptitude obligatoire de la mission manque (progression très lente).
 *   Blocage    : gain nul si l'aptitude requise par l'objectif lui-même est absente, ou si
 *                aucun héros n'est en état de contribuer.
 *
 * Le temps écoulé intervient via le nombre d'étapes calculées par MissionSimulationService.
 */
final class ObjectiveProgressCalculator
{
    public const GAIN_BASE = 2.0;
    public const EXIGENCE_PAR_DEFAUT = 50;
    public const FACTEUR_APTITUDE_MANQUANTE = 0.1;

    /**
     * @param list<Hero>         $presents  héros sur place et en état de contribuer
     * @param array<string, int> $puissance puissance de ces héros (TeamPowerCalculator)
     */
    public function calculer(ObjectifMission $objectif, Mission $mission, array $presents, array $puissance, float $menace): ResultatProgression
    {
        if ($objectif->isTermine()) {
            return new ResultatProgression(0.0, false, 'Objectif terminé');
        }
        if ([] === $presents) {
            return new ResultatProgression(0.0, true, 'Aucun héros en état d\'agir sur place');
        }

        $aptitude = $objectif->getAptitudeRequise();
        if (null !== $aptitude && !$this->equipePossede($presents, $aptitude)) {
            return new ResultatProgression(0.0, true, sprintf('Compétence requise absente : %s', $aptitude->getNom()));
        }

        $gain = self::GAIN_BASE
            * $this->facteurStatistique($objectif, $mission, $puissance)
            * $this->facteurEquipe(\count($presents), $mission->getTailleEquipeMinimale())
            * $this->facteurMenace($menace)
            * ($mission->getDifficulte() ?? Difficulte::C)->multiplicateur();

        $raison = null;
        foreach ($mission->getAptitudesRequises() as $requise) {
            if (!$this->equipePossede($presents, $requise)) {
                $gain *= self::FACTEUR_APTITUDE_MANQUANTE;
                $raison = sprintf('Progression ralentie : %s manquante', $requise->getNom());
                break;
            }
        }

        return new ResultatProgression(round($gain, 4), false, $raison);
    }

    /** @param array<string, int> $puissance */
    public function facteurStatistique(ObjectifMission $objectif, Mission $mission, array $puissance): float
    {
        $cle = $objectif->getStatistiqueCle();
        if (null !== $cle && isset($puissance[$cle->value])) {
            $exigence = $mission->getStatRequise($cle) ?: self::EXIGENCE_PAR_DEFAUT;

            return self::borner($puissance[$cle->value] / max(1, $exigence));
        }

        $requises = $mission->getStatsRequises();
        if ([] !== $requises) {
            $ratios = [];
            foreach ($requises as $stat => $requis) {
                $ratios[] = self::borner(($puissance[$stat] ?? 0) / max(1, $requis));
            }

            return array_sum($ratios) / \count($ratios);
        }

        return [] === $puissance ? 0.2 : self::borner(array_sum($puissance) / \count($puissance) / 60);
    }

    public function facteurEquipe(int $presents, int $tailleMinimale): float
    {
        return min(1.3, sqrt(max(0, $presents) / max(1, $tailleMinimale)));
    }

    public function facteurMenace(float $menace): float
    {
        return max(0.25, 1 - max(0.0, $menace) / 130);
    }

    /** @param list<Hero> $equipe */
    private function equipePossede(array $equipe, \App\Entity\Aptitude $aptitude): bool
    {
        foreach ($equipe as $hero) {
            if ($hero->getAptitudes()->contains($aptitude)) {
                return true;
            }
        }

        return false;
    }

    private static function borner(float $ratio): float
    {
        return max(0.2, min(1.5, $ratio));
    }
}
