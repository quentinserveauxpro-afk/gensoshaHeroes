<?php

namespace App\Enum;

enum BonusOrigine: string
{
    case EQUIPEMENT = 'EQUIPEMENT';
    case MEDICAL = 'MEDICAL';
    case ENTRAINEMENT = 'ENTRAINEMENT';
    case MISSION = 'MISSION';
    case EVENEMENT = 'EVENEMENT';

    public function label(): string
    {
        return match ($this) {
            self::EQUIPEMENT => 'Équipement',
            self::MEDICAL => 'Médical',
            self::ENTRAINEMENT => 'Entraînement',
            self::MISSION => 'Récompense de mission',
            self::EVENEMENT => 'Événement',
        };
    }
}
