<?php

namespace App\Service\Mission;

use App\Entity\Mission;
use Psr\Clock\ClockInterface;

/**
 * Compte à rebours d'une mission. Source de vérité : le serveur.
 * temps restant = max(0, dateFinPrevue − maintenant).
 */
final class MissionTimer
{
    public const LIBELLE_TERMINE = 'Mission terminée';

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function secondesRestantes(Mission $mission): int
    {
        $echeance = $mission->getDateFinPrevue();
        if (null === $echeance) {
            return 0;
        }

        return max(0, $echeance->getTimestamp() - $this->clock->now()->getTimestamp());
    }

    public function estEchu(Mission $mission): bool
    {
        return 0 === $this->secondesRestantes($mission);
    }

    public function formater(int $secondes): string
    {
        if ($secondes <= 0) {
            return self::LIBELLE_TERMINE;
        }
        $heures = intdiv($secondes, 3600);
        $minutes = intdiv($secondes % 3600, 60);

        return sprintf('%02d:%02d:%02d', $heures, $minutes, $secondes % 60);
    }

    public function snapshot(Mission $mission): TimerSnapshot
    {
        $secondes = $this->secondesRestantes($mission);

        return new TimerSnapshot($secondes, 0 === $secondes, $mission->getDateFinPrevue(), $this->formater($secondes));
    }
}
