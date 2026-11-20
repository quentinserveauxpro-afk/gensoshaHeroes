<?php

namespace App\Enum;

/** Motif médical (fictif) d'un séjour à l'infirmerie. */
enum MotifInfirmerie: string
{
    case SANTE_CRITIQUE = 'SANTE_CRITIQUE';
    case EPUISEMENT = 'EPUISEMENT';
    case DECISION_MEDICALE = 'DECISION_MEDICALE';
    case RECUPERATION_POST_MISSION = 'RECUPERATION_POST_MISSION';
    case BLESSURE = 'BLESSURE';

    public function label(): string
    {
        return match ($this) {
            self::SANTE_CRITIQUE => 'Santé critique',
            self::EPUISEMENT => 'Épuisement',
            self::DECISION_MEDICALE => 'Décision médicale',
            self::RECUPERATION_POST_MISSION => 'Récupération après mission',
            self::BLESSURE => 'Blessure',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::SANTE_CRITIQUE => 'heart_broken',
            self::EPUISEMENT => 'battery_alert',
            self::DECISION_MEDICALE => 'medical_services',
            self::RECUPERATION_POST_MISSION => 'self_improvement',
            self::BLESSURE => 'healing',
        };
    }

    /** Statut héros existant correspondant pendant le séjour (aucun nouveau statut héros). */
    public function statutHero(): HeroStatut
    {
        return self::EPUISEMENT === $this ? HeroStatut::REPOS : HeroStatut::BLESSE;
    }

    /** Motifs proposés pour une admission manuelle. @return list<self> */
    public static function manuels(): array
    {
        return [self::DECISION_MEDICALE, self::BLESSURE, self::EPUISEMENT, self::SANTE_CRITIQUE];
    }
}
