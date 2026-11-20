<?php

namespace App\Simulation;

final class RapportExecution
{
    public int $deplacementsCrees = 0;
    public int $deplacementsActualises = 0;
    public int $arrivees = 0;
    public int $deplacementsInterrompus = 0;
    public int $missionsSimulees = 0;
    public int $missionsVerrouillees = 0;
    public int $missionsTerminees = 0;
    public int $etapes = 0;

    public function resume(): string
    {
        return sprintf(
            'déplacements : %d créé(s), %d actualisé(s), %d arrivée(s), %d interrompu(s) • missions : %d simulée(s), %d étape(s), %d terminée(s), %d ignorée(s) (verrou)',
            $this->deplacementsCrees, $this->deplacementsActualises, $this->arrivees, $this->deplacementsInterrompus,
            $this->missionsSimulees, $this->etapes, $this->missionsTerminees, $this->missionsVerrouillees,
        );
    }
}
