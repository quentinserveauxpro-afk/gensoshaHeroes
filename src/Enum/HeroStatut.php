<?php

namespace App\Enum;

enum HeroStatut: string
{
    case DISPONIBLE = 'DISPONIBLE';
    case EN_MISSION = 'EN_MISSION';
    case BLESSE = 'BLESSE';
    case INDISPONIBLE = 'INDISPONIBLE';
    case REPOS = 'REPOS';

    public function label(): string
    {
        return match ($this) {
            self::DISPONIBLE => 'Disponible',
            self::EN_MISSION => 'En mission',
            self::BLESSE => 'Blessé',
            self::INDISPONIBLE => 'Indisponible',
            self::REPOS => 'Repos',
        };
    }

    /** Ton visuel du badge (ok, warn, danger, muted). */
    public function tone(): string
    {
        return match ($this) {
            self::DISPONIBLE => 'ok',
            self::EN_MISSION => 'warn',
            self::BLESSE => 'danger',
            self::INDISPONIBLE, self::REPOS => 'muted',
        };
    }
}
