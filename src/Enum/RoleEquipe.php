<?php

namespace App\Enum;

/** Rôle tenu par un héros au sein d'une affectation. */
enum RoleEquipe: string
{
    case LEAD = 'LEAD';
    case MEMBRE = 'MEMBRE';
    case RESERVISTE = 'RESERVISTE';

    public function label(): string
    {
        return match ($this) {
            self::LEAD => 'Chef d\'escouade',
            self::MEMBRE => 'Membre',
            self::RESERVISTE => 'Réserviste',
        };
    }
}
