<?php

namespace App\Enum;

enum NiveauUrgence: string
{
    case FAIBLE = 'FAIBLE';
    case MOYENNE = 'MOYENNE';
    case HAUTE = 'HAUTE';
    case CRITIQUE = 'CRITIQUE';

    public function label(): string
    {
        return match ($this) {
            self::FAIBLE => 'Faible',
            self::MOYENNE => 'Moyenne',
            self::HAUTE => 'Haute',
            self::CRITIQUE => 'Critique',
        };
    }

    public function poids(): int
    {
        return match ($this) {
            self::FAIBLE => 1,
            self::MOYENNE => 2,
            self::HAUTE => 3,
            self::CRITIQUE => 4,
        };
    }

    public function isUrgent(): bool
    {
        return $this->poids() >= 3;
    }

    public function tone(): string
    {
        return match ($this) {
            self::FAIBLE => 'muted',
            self::MOYENNE => 'ok',
            self::HAUTE => 'warn',
            self::CRITIQUE => 'danger',
        };
    }
}
