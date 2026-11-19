<?php

namespace App\Service\Mission;

use App\Entity\Hero;
use App\Entity\Mission;
use App\Entity\Synergie;

/**
 * Détermine les synergies actives pour une composition d'équipe.
 *
 * Une synergie est active si :
 *  - elle est marquée active ;
 *  - au moins `nombreMinimalHeros` (par défaut : tous) de ses héros sont dans l'équipe ;
 *  - la mission atteint son rang minimal éventuel.
 */
final class SynergyResolver
{
    /**
     * @param list<Hero> $equipe
     *
     * @return list<Synergie>
     */
    public function synergiesActives(array $equipe, ?Mission $mission = null): array
    {
        $ids = [];
        foreach ($equipe as $hero) {
            $ids[spl_object_id($hero)] = true;
        }

        $actives = [];
        foreach ($equipe as $hero) {
            foreach ($hero->getSynergies() as $synergie) {
                $cle = spl_object_id($synergie);
                if (isset($actives[$cle]) || !$this->estActive($synergie, $ids, $mission)) {
                    continue;
                }
                $actives[$cle] = $synergie;
            }
        }

        return array_values($actives);
    }

    /** @param array<int, true> $idsEquipe */
    private function estActive(Synergie $synergie, array $idsEquipe, ?Mission $mission): bool
    {
        if (!$synergie->isActif()) {
            return false;
        }
        $minimum = $synergie->getDifficulteMinimale();
        if (null !== $minimum && null !== $mission
            && ($mission->getDifficulte()?->rang() ?? 0) < $minimum->rang()) {
            return false;
        }

        $presents = 0;
        foreach ($synergie->getHeroes() as $hero) {
            if (isset($idsEquipe[spl_object_id($hero)])) {
                ++$presents;
            }
        }
        $requis = $synergie->getNombreMinimalHeros() ?? $synergie->getHeroes()->count();

        return $presents >= max(2, $requis);
    }
}
