<?php

namespace App\Enum;

enum MouvementStatut: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case EN_DEPLACEMENT = 'EN_DEPLACEMENT';
    case ARRIVE = 'ARRIVE';
    case INTERROMPU = 'INTERROMPU';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente de départ',
            self::EN_DEPLACEMENT => 'En déplacement',
            self::ARRIVE => 'Arrivé sur place',
            self::INTERROMPU => 'Déplacement interrompu',
        };
    }

    public function isEnCours(): bool
    {
        return \in_array($this, [self::EN_ATTENTE, self::EN_DEPLACEMENT], true);
    }
}
