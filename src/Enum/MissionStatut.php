<?php

namespace App\Enum;

enum MissionStatut: string
{
    case BROUILLON = 'BROUILLON';
    case EN_ATTENTE = 'EN_ATTENTE';
    case EN_COURS = 'EN_COURS';
    case TERMINEE = 'TERMINEE';
    case ECHOUEE = 'ECHOUEE';
    case ANNULEE = 'ANNULEE';

    public function label(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon',
            self::EN_ATTENTE => 'En attente',
            self::EN_COURS => 'En cours',
            self::TERMINEE => 'Terminée',
            self::ECHOUEE => 'Échouée',
            self::ANNULEE => 'Annulée',
        };
    }

    public function isActive(): bool
    {
        return \in_array($this, [self::EN_ATTENTE, self::EN_COURS], true);
    }

    public function isClosed(): bool
    {
        return \in_array($this, [self::TERMINEE, self::ECHOUEE, self::ANNULEE], true);
    }

    /** @return list<self> */
    public static function actifs(): array
    {
        return [self::EN_ATTENTE, self::EN_COURS];
    }
}
