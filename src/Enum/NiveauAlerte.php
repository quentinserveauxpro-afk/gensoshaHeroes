<?php

namespace App\Enum;

enum NiveauAlerte: string
{
    case INFO = 'INFO';
    case ALERTE = 'ALERTE';
    case CRITIQUE = 'CRITIQUE';

    public function tone(): string
    {
        return match ($this) {
            self::INFO => 'ok',
            self::ALERTE => 'warn',
            self::CRITIQUE => 'danger',
        };
    }
}
