<?php

namespace App\Enum;

/**
 * Catégorie détaillée d'un incident signalé par un citoyen.
 *
 * Ajout additif : l'enum existante RequeteCategorie (4 valeurs) n'est pas modifiable ;
 * chaque cas est rattaché à une catégorie existante via versCategorieExistante().
 */
enum CategorieIncident: string
{
    case ACCIDENT = 'ACCIDENT';
    case INCENDIE = 'INCENDIE';
    case AGRESSION = 'AGRESSION';
    case CATASTROPHE = 'CATASTROPHE';
    case SUPER_VILAIN = 'SUPER_VILAIN';
    case ASSISTANCE_PUBLIQUE = 'ASSISTANCE_PUBLIQUE';
    case ASSISTANCE_ANIMALE = 'ASSISTANCE_ANIMALE';
    case AUTRE = 'AUTRE';

    public function label(): string
    {
        return match ($this) {
            self::ACCIDENT => 'Accident',
            self::INCENDIE => 'Incendie',
            self::AGRESSION => 'Agression',
            self::CATASTROPHE => 'Catastrophe',
            self::SUPER_VILAIN => 'Super-vilain',
            self::ASSISTANCE_PUBLIQUE => 'Assistance publique',
            self::ASSISTANCE_ANIMALE => 'Assistance animale',
            self::AUTRE => 'Autre',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::ACCIDENT => 'car_crash',
            self::INCENDIE => 'local_fire_department',
            self::AGRESSION => 'front_hand',
            self::CATASTROPHE => 'flood',
            self::SUPER_VILAIN => 'skull',
            self::ASSISTANCE_PUBLIQUE => 'support',
            self::ASSISTANCE_ANIMALE => 'pets',
            self::AUTRE => 'help',
        };
    }

    /** Catégorie de l'enum historique utilisée par les écrans et statistiques existants. */
    public function versCategorieExistante(): RequeteCategorie
    {
        return match ($this) {
            self::ACCIDENT, self::INCENDIE, self::AGRESSION => RequeteCategorie::URGENCE_VITALE,
            self::CATASTROPHE => RequeteCategorie::CATASTROPHE,
            self::SUPER_VILAIN => RequeteCategorie::SUPER_VILAIN,
            self::ASSISTANCE_PUBLIQUE, self::ASSISTANCE_ANIMALE, self::AUTRE => RequeteCategorie::ASSISTANCE_PUBLIQUE,
        };
    }

    public static function depuisCategorieExistante(RequeteCategorie $categorie): self
    {
        return match ($categorie) {
            RequeteCategorie::URGENCE_VITALE => self::ACCIDENT,
            RequeteCategorie::CATASTROPHE => self::CATASTROPHE,
            RequeteCategorie::SUPER_VILAIN => self::SUPER_VILAIN,
            RequeteCategorie::ASSISTANCE_PUBLIQUE => self::ASSISTANCE_PUBLIQUE,
        };
    }
}
