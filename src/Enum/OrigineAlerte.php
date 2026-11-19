<?php

namespace App\Enum;

/** Canal d'origine d'une requête citoyenne. */
enum OrigineAlerte: string
{
    case CIVIL = 'CIVIL';
    case POLICE = 'POLICE';
    case POMPIERS = 'POMPIERS';
    case CAPTEUR = 'CAPTEUR';

    public function label(): string
    {
        return match ($this) {
            self::CIVIL => 'Civil (112)',
            self::POLICE => 'Police',
            self::POMPIERS => 'Pompiers',
            self::CAPTEUR => 'Capteur IA Sat',
        };
    }
}
