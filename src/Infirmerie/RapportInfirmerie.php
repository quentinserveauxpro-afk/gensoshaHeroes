<?php

namespace App\Infirmerie;

/** Bilan d'un passage de app:infirmary:update. */
final class RapportInfirmerie
{
    public bool $verrouille = false;
    public int $admissions = 0;
    public int $sejoursActualises = 0;
    public int $arrivees = 0;
    public int $retablis = 0;
    public int $sorties = 0;
    public int $ignores = 0;

    public function resume(): string
    {
        if ($this->verrouille) {
            return 'Infirmerie : passage déjà en cours dans un autre processus.';
        }

        return sprintf('Infirmerie : %d admission(s), %d séjour(s) actualisé(s), %d arrivée(s) au QG, %d rétabli(s), %d sortie(s)%s.',
            $this->admissions, $this->sejoursActualises, $this->arrivees, $this->retablis, $this->sorties,
            $this->ignores > 0 ? sprintf(', %d héros ignoré(s) (mission verrouillée)', $this->ignores) : '');
    }
}
