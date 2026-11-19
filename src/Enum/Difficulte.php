<?php

namespace App\Enum;

/**
 * Rang de difficulté d'une mission. Stocké dans la colonne existante
 * Mission::$niveauDeDifficulte (string).
 */
enum Difficulte: string
{
    case D = 'D';
    case C = 'C';
    case B = 'B';
    case A = 'A';
    case S = 'S';

    /** Niveau de héros conseillé pour ce rang. */
    public function niveauRecommande(): int
    {
        return match ($this) {
            self::D => 10,
            self::C => 20,
            self::B => 30,
            self::A => 40,
            self::S => 50,
        };
    }

    /** Multiplicateur appliqué au score de base du calcul de réussite. */
    public function multiplicateur(): float
    {
        return match ($this) {
            self::D => 1.10,
            self::C => 1.00,
            self::B => 0.95,
            self::A => 0.90,
            self::S => 0.85,
        };
    }

    /** XP gagnée par héros en cas de succès. */
    public function experienceRecompense(): int
    {
        return match ($this) {
            self::D => 200,
            self::C => 400,
            self::B => 800,
            self::A => 1500,
            self::S => 3000,
        };
    }

    public function label(): string
    {
        return 'Rang '.$this->value;
    }

    public function rang(): int
    {
        return match ($this) {
            self::D => 1,
            self::C => 2,
            self::B => 3,
            self::A => 4,
            self::S => 5,
        };
    }
}
