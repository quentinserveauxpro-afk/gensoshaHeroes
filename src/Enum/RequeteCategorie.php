<?php

namespace App\Enum;

enum RequeteCategorie: string
{
    case URGENCE_VITALE = 'URGENCE_VITALE';
    case SUPER_VILAIN = 'SUPER_VILAIN';
    case CATASTROPHE = 'CATASTROPHE';
    case ASSISTANCE_PUBLIQUE = 'ASSISTANCE_PUBLIQUE';

    public function label(): string
    {
        return match ($this) {
            self::URGENCE_VITALE => 'Urgence vitale',
            self::SUPER_VILAIN => 'Menace métahumaine',
            self::CATASTROPHE => 'Catastrophe',
            self::ASSISTANCE_PUBLIQUE => 'Assistance publique',
        };
    }
}
