<?php

namespace App\Service\Mission;

final class DeploymentException extends \DomainException
{
    /** @param list<string> $erreurs */
    public function __construct(public readonly array $erreurs)
    {
        parent::__construct(implode(' ', $erreurs));
    }
}
