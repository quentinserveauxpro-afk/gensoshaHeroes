<?php

namespace App\Command;

use App\Infirmerie\InfirmaryUpdateRunner;
use App\Simulation\MissionUpdateRunner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

/**
 * Moteur temps réel : déplacements des héros et simulation des missions en cours,
 * puis passage de l'infirmerie (désactivable avec --sans-infirmerie).
 *
 *   php bin/console app:missions:update                 # un passage (cron, Messenger, CI)
 *   php bin/console app:missions:update --watch         # worker : un passage toutes les 3 s
 *   php bin/console app:missions:update --watch --intervalle=2 --iterations=100
 *
 * Idempotente : relancée immédiatement, elle ne recalcule aucune étape déjà faite.
 * Un verrou global empêche deux workers concurrents ; un verrou par mission empêche
 * le double traitement d'une même mission.
 */
#[AsCommand(name: 'app:missions:update', description: 'Fait avancer les héros et simule les missions en cours (temps réel)')]
final class MissionsUpdateCommand
{
    private bool $arret = false;

    public function __construct(
        private readonly MissionUpdateRunner $runner,
        private readonly LockFactory $verrous,
        private readonly EntityManagerInterface $em,
        private readonly ?InfirmaryUpdateRunner $infirmerie = null,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Boucle en continu (worker)')] bool $watch = false,
        #[Option(description: 'Secondes entre deux passages en mode --watch (2 à 60)')] int $intervalle = 3,
        #[Option(description: 'Nombre maximal de passages en mode --watch (0 = illimité)')] int $iterations = 0,
        #[Option(description: 'Ne pas exécuter le passage de l\'infirmerie (app:infirmary:update)')] bool $sansInfirmerie = false,
    ): int {
        $intervalle = max(2, min(60, $intervalle));
        $verrou = $this->verrous->createLock('app-missions-update', 3 * MissionUpdateRunner::TTL_VERROU);
        if (!$verrou->acquire(false)) {
            $io->warning('Un autre processus app:missions:update est déjà en cours : rien à faire.');

            return Command::SUCCESS;
        }

        if ($watch && \function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(\SIGTERM, fn () => $this->arret = true);
            pcntl_signal(\SIGINT, fn () => $this->arret = true);
        }

        try {
            $n = 0;
            do {
                $rapport = $this->runner->executer();
                ++$n;
                if ($io->isVerbose() || !$watch) {
                    $io->writeln(sprintf('[%s] %s', date('H:i:s'), $rapport->resume()));
                }
                if (!$sansInfirmerie && null !== $this->infirmerie) {
                    $this->em->clear();
                    $bilan = $this->infirmerie->executer();
                    if ($io->isVerbose() || !$watch) {
                        $io->writeln(sprintf('[%s] %s', date('H:i:s'), $bilan->resume()));
                    }
                }
                $this->em->clear();
                $verrou->refresh();
                if (!$watch || $this->arret || ($iterations > 0 && $n >= $iterations)) {
                    break;
                }
                sleep($intervalle);
            } while (!$this->arret);
        } finally {
            $verrou->release();
        }

        if ($watch) {
            $io->success(sprintf('Worker arrêté après %d passage(s).', $n));
        }

        return Command::SUCCESS;
    }
}
