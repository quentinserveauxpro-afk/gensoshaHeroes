<?php

namespace App\Enum;

/** Statistique ciblée par un bonus ou une synergie. GLOBAL agit directement sur le taux de réussite. */
enum Statistique: string
{
    case COMBAT = 'COMBAT';
    case INTELLIGENCE = 'INTELLIGENCE';
    case CHARISME = 'CHARISME';
    case MOBILITE = 'MOBILITE';
    case VIGUEUR = 'VIGUEUR';
    case GLOBAL = 'GLOBAL';

    public function label(): string
    {
        return match ($this) {
            self::COMBAT => 'Combat',
            self::INTELLIGENCE => 'Intelligence',
            self::CHARISME => 'Charisme',
            self::MOBILITE => 'Mobilité',
            self::VIGUEUR => 'Vigueur',
            self::GLOBAL => 'Global',
        };
    }

    /** @return list<self> les cinq statistiques de héros (sans GLOBAL) */
    public static function heroStats(): array
    {
        return [self::COMBAT, self::INTELLIGENCE, self::CHARISME, self::MOBILITE, self::VIGUEUR];
    }
}
