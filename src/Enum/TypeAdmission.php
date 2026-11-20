<?php

namespace App\Enum;

/** Origine d'une admission à l'infirmerie. */
enum TypeAdmission: string
{
    case AUTOMATIQUE = 'AUTOMATIQUE';
    case MANUELLE = 'MANUELLE';
    case POST_MISSION = 'POST_MISSION';

    public function label(): string
    {
        return match ($this) {
            self::AUTOMATIQUE => 'Rapatriement automatique',
            self::MANUELLE => 'Admission manuelle',
            self::POST_MISSION => 'Après mission',
        };
    }
}
