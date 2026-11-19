<?php

namespace App\Service\Mission;

use App\Entity\Aptitude;
use App\Entity\Synergie;

/** Résultat détaillé du MissionSuccessCalculator. */
final readonly class SuccessPrognosis
{
    /**
     * @param array<string, int>                              $puissance            puissance d'équipe par statistique (0-100)
     * @param array<string, array{requis: int, actuel: int}> $statsInsuffisantes   exigences non couvertes par le meilleur héros
     * @param list<Synergie>                                  $synergiesActives
     * @param list<Aptitude>                                  $aptitudesManquantes
     */
    public function __construct(
        public int $taux,
        public float $scoreStatistiques,
        public float $scoreNiveau,
        public float $scoreSante,
        public float $scoreProximite,
        public float $penaliteMenace,
        public float $bonusGlobal,
        public array $puissance,
        public array $statsInsuffisantes,
        public array $synergiesActives,
        public int $bonusSynergieTotal,
        public array $aptitudesManquantes,
        public int $tailleEquipe,
    ) {
    }

    public function qualiteSynergie(): string
    {
        return match (true) {
            $this->tailleEquipe < 2 => 'Aucune',
            $this->bonusSynergieTotal >= 20 => 'Optimale',
            $this->bonusSynergieTotal >= 8 => 'Bonne',
            $this->bonusSynergieTotal > 0 => 'Correcte',
            default => 'Neutre',
        };
    }
}
