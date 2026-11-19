<?php

namespace App\Enum;

enum RequeteStatut: string
{
    case NOUVELLE = 'NOUVELLE';
    case EN_QUALIFICATION = 'EN_QUALIFICATION';
    case CONVERTIE = 'CONVERTIE';
    case CLASSEE = 'CLASSEE';

    public function label(): string
    {
        return match ($this) {
            self::NOUVELLE => 'Nouvelle',
            self::EN_QUALIFICATION => 'En cours de qualification',
            self::CONVERTIE => 'Convertie en mission',
            self::CLASSEE => 'Classée sans suite',
        };
    }

    public function isOuverte(): bool
    {
        return \in_array($this, [self::NOUVELLE, self::EN_QUALIFICATION], true);
    }
}
