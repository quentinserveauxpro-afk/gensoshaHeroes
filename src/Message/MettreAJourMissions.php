<?php

namespace App\Message;

/**
 * Déclenche un passage du moteur temps réel via Messenger (alternative à la commande).
 * Exemple : $bus->dispatch(new MettreAJourMissions()) depuis un planificateur.
 */
final readonly class MettreAJourMissions
{
}
