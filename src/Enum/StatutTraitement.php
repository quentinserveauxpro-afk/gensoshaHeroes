<?php

namespace App\Enum;

/**
 * Statut détaillé du traitement d'une requête citoyenne par les opérateurs missions.
 *
 * Ajout additif : l'enum existante RequeteStatut (4 valeurs) n'est pas modifiable.
 * Chaque statut détaillé est projeté sur un statut historique (versStatutExistant())
 * afin que les écrans, compteurs et règles existants restent cohérents.
 */
enum StatutTraitement: string
{
    case NOUVELLE = 'NOUVELLE';
    case EN_ANALYSE = 'EN_ANALYSE';
    case INFORMATIONS_REQUISES = 'INFORMATIONS_REQUISES';
    case VALIDEE = 'VALIDEE';
    case CONVERTIE_EN_MISSION = 'CONVERTIE_EN_MISSION';
    case REJETEE = 'REJETEE';
    case CLASSEE_SANS_SUITE = 'CLASSEE_SANS_SUITE';

    public function label(): string
    {
        return match ($this) {
            self::NOUVELLE => 'Nouvelle',
            self::EN_ANALYSE => 'En analyse',
            self::INFORMATIONS_REQUISES => 'Informations requises',
            self::VALIDEE => 'Validée',
            self::CONVERTIE_EN_MISSION => 'Convertie en mission',
            self::REJETEE => 'Rejetée',
            self::CLASSEE_SANS_SUITE => 'Classée sans suite',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NOUVELLE => 'warn',
            self::EN_ANALYSE, self::INFORMATIONS_REQUISES => 'muted',
            self::VALIDEE, self::CONVERTIE_EN_MISSION => 'ok',
            self::REJETEE, self::CLASSEE_SANS_SUITE => 'danger',
        };
    }

    public function versStatutExistant(): RequeteStatut
    {
        return match ($this) {
            self::NOUVELLE => RequeteStatut::NOUVELLE,
            self::EN_ANALYSE, self::INFORMATIONS_REQUISES, self::VALIDEE => RequeteStatut::EN_QUALIFICATION,
            self::CONVERTIE_EN_MISSION => RequeteStatut::CONVERTIE,
            self::REJETEE, self::CLASSEE_SANS_SUITE => RequeteStatut::CLASSEE,
        };
    }

    public static function depuisStatutExistant(RequeteStatut $statut): self
    {
        return match ($statut) {
            RequeteStatut::NOUVELLE => self::NOUVELLE,
            RequeteStatut::EN_QUALIFICATION => self::EN_ANALYSE,
            RequeteStatut::CONVERTIE => self::CONVERTIE_EN_MISSION,
            RequeteStatut::CLASSEE => self::CLASSEE_SANS_SUITE,
        };
    }

    public function isOuverte(): bool
    {
        return \in_array($this, [self::NOUVELLE, self::EN_ANALYSE, self::INFORMATIONS_REQUISES, self::VALIDEE], true);
    }

    /** Statuts qu'un opérateur peut choisir lors de la qualification. */
    public static function qualifiables(): array
    {
        return [self::EN_ANALYSE, self::INFORMATIONS_REQUISES, self::VALIDEE];
    }
}
