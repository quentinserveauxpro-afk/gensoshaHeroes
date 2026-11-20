<?php

namespace App\Simulation;

/**
 * Taux de réussite estimé pendant l'engagement (0-100), recalculé à chaque passage du moteur.
 *
 *   si tous les objectifs obligatoires sont atteints : 100
 *   si plus aucun héros ne peut agir ni n'est en route : 0
 *   sinon :
 *     p        = progression moyenne des objectifs obligatoires (0-100)
 *     capacité = étapes restantes avant l'échéance × gain moyen par étape
 *     délai    = borne(capacité / (100 − p), 0, 1)
 *     taux     = 100 × (0,3 × p / 100 + 0,7 × délai × (0,4 + 0,6 × santé moyenne des héros présents))
 *
 * Avant l'arrivée du premier héros, le pronostic de déploiement (MissionSuccessCalculator) est conservé.
 */
final class LiveSuccessRateCalculator
{
    public function calculer(float $progression, float $gainMoyenParEtape, int $etapesRestantes, float $santeMoyenne, bool $objectifsAtteints, bool $equipeOperationnelle): int
    {
        if ($objectifsAtteints) {
            return 100;
        }
        if (!$equipeOperationnelle) {
            return 0;
        }
        $p = max(0.0, min(100.0, $progression));
        $reste = 100 - $p;
        $capacite = max(0, $etapesRestantes) * max(0.0, $gainMoyenParEtape);
        $delai = $reste <= 0 ? 1.0 : max(0.0, min(1.0, $capacite / $reste));
        $sante = max(0.0, min(1.0, $santeMoyenne));

        return (int) round(max(0.0, min(100.0, 100 * (0.3 * $p / 100 + 0.7 * $delai * (0.4 + 0.6 * $sante)))));
    }
}
