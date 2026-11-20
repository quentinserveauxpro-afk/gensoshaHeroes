<?php

namespace App\Infirmerie;

use App\Entity\Mission;

/** Effets du retrait d'un héros de sa mission (aperçu avant confirmation, puis rapport). */
final readonly class ConsequencesRetrait
{
    /**
     * @param list<string> $equipeRestante    noms des héros encore engagés
     * @param list<string> $synergiesPerdues
     * @param list<string> $aptitudesManquantes aptitudes obligatoires qui ne seraient plus couvertes
     */
    public function __construct(
        public ?Mission $mission,
        public array $equipeRestante = [],
        public ?int $tauxAvant = null,
        public ?int $tauxApres = null,
        public array $synergiesPerdues = [],
        public array $aptitudesManquantes = [],
    ) {
    }

    public function aucuneMission(): bool
    {
        return null === $this->mission;
    }

    public function equipeVide(): bool
    {
        return null !== $this->mission && [] === $this->equipeRestante;
    }
}
