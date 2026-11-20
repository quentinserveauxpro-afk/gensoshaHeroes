<?php

namespace App\MessageHandler;

use App\Message\MettreAJourMissions;
use App\Simulation\MissionUpdateRunner;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MettreAJourMissionsHandler
{
    public function __construct(
        private readonly MissionUpdateRunner $runner,
        private readonly LockFactory $verrous,
    ) {
    }

    public function __invoke(MettreAJourMissions $message): void
    {
        $verrou = $this->verrous->createLock('app-missions-update', 3 * MissionUpdateRunner::TTL_VERROU);
        if (!$verrou->acquire(false)) {
            return;
        }
        try {
            $this->runner->executer();
        } finally {
            $verrou->release();
        }
    }
}
