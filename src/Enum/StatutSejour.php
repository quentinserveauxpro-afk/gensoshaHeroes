<?php

namespace App\Enum;

/** Cycle de vie d'un séjour : retour au QG → traitement → rétabli → sorti (ou annulé). */
enum StatutSejour: string
{
    case RETOUR_EN_ATTENTE = 'RETOUR_EN_ATTENTE';
    case RETOUR_QG = 'RETOUR_QG';
    case EN_TRAITEMENT = 'EN_TRAITEMENT';
    case RETABLI = 'RETABLI';
    case SORTI = 'SORTI';
    case ANNULE = 'ANNULE';

    public function label(): string
    {
        return match ($this) {
            self::RETOUR_EN_ATTENTE => 'Retour en préparation',
            self::RETOUR_QG => 'Retour vers le QG',
            self::EN_TRAITEMENT => 'En traitement',
            self::RETABLI => 'Rétabli — sortie à valider',
            self::SORTI => 'Sorti',
            self::ANNULE => 'Annulé',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::RETOUR_EN_ATTENTE, self::RETOUR_QG => 'warn',
            self::EN_TRAITEMENT => 'danger',
            self::RETABLI, self::SORTI => 'ok',
            self::ANNULE => 'muted',
        };
    }

    /** Séjour en cours : le héros est médicalement indisponible et occupe un lit. */
    public function isActif(): bool
    {
        return !\in_array($this, [self::SORTI, self::ANNULE], true);
    }

    public function isRetour(): bool
    {
        return \in_array($this, [self::RETOUR_EN_ATTENTE, self::RETOUR_QG], true);
    }

    /** @return list<self> */
    public static function actifs(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $s) => $s->isActif()));
    }
}
