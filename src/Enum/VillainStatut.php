<?php

namespace App\Enum;

enum VillainStatut: string
{
    case EN_LIBERTE = 'EN_LIBERTE';
    case LOCALISE = 'LOCALISE';
    case CAPTURE = 'CAPTURE';
    case NEUTRALISE = 'NEUTRALISE';

    public function label(): string
    {
        return match ($this) {
            self::EN_LIBERTE => 'En liberté',
            self::LOCALISE => 'Localisé',
            self::CAPTURE => 'Capturé',
            self::NEUTRALISE => 'Neutralisé',
        };
    }
}
