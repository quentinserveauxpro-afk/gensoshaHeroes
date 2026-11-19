<?php

namespace App\Service\Mission;

/** Résultat de la validation d'une composition d'équipe. */
final readonly class DeploymentReport
{
    /**
     * @param list<string> $erreurs        avertissements bloquants
     * @param list<string> $avertissements avertissements non bloquants
     */
    public function __construct(
        public array $erreurs,
        public array $avertissements,
    ) {
    }

    public function estValide(): bool
    {
        return [] === $this->erreurs;
    }
}
