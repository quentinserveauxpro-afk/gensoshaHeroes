<?php

namespace App\Enum;

enum AffectationStatut: string
{
    case AFFECTE = 'AFFECTE';
    case SUR_PLACE = 'SUR_PLACE';
    case TERMINEE = 'TERMINEE';
    case RETIRE = 'RETIRE';

    public function label(): string
    {
        return match ($this) {
            self::AFFECTE => 'En route',
            self::SUR_PLACE => 'Sur place',
            self::TERMINEE => 'Terminée',
            self::RETIRE => 'Retiré',
        };
    }

    public function isActive(): bool
    {
        return \in_array($this, [self::AFFECTE, self::SUR_PLACE], true);
    }
}
