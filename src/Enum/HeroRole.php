<?php

namespace App\Enum;

/** Rôle tactique d'un héros (spécialisation d'escouade). */
enum HeroRole: string
{
    case ASSAUT = 'ASSAUT';
    case CONTROLE = 'CONTROLE';
    case SOUTIEN = 'SOUTIEN';
    case ECLAIREUR = 'ECLAIREUR';

    public function label(): string
    {
        return match ($this) {
            self::ASSAUT => 'Assaut',
            self::CONTROLE => 'Contrôle',
            self::SOUTIEN => 'Soutien',
            self::ECLAIREUR => 'Éclaireur',
        };
    }
}
