<?php

namespace App\Command;

use App\Infirmerie\InfirmaryUpdateRunner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Infirmerie : rapatriements automatiques, retours au QG, récupération et sorties.
 *
 *   php bin/console app:infirmary:update              # un passage (cron, Messenger, CI)
 *   php bin/console app:infirmary:update --watch      # worker : un passage toutes les 5 s
 *
 * Le worker des missions (app:missions:update --watch) exécute aussi ce passage à chaque
 * itération : un seul processus suffit. Idempotente et protégée par un verrou global.
 */
#[AsCommand(name: 'app:infirmary:update', description: 'Rapatrie les héros en état critique et fait progresser leur récupération')]
final class InfirmaryUpdateCommand
{
    private bool $arret = false;

    public function __construct(
        private readonly InfirmaryUpdateRunner $runner,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Boucle en continu (worker)')] bool $watch = false,
        #[Option(description: 'Secondes entre deux passages en mode --watch (2 à 300)')] int $intervalle = 5,
        #[Option(description: 'Nombre maximal de passages en mode --watch (0 = illimité)')] int $iterations = 0,
    ): int {
        $intervalle = max(2, min(300, $intervalle));
        if ($watch && \function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(\SIGTERM, fn () => $this->arret = true);
            pcntl_signal(\SIGINT, fn () => $this->arret = true);
        }

        $n = 0;
        do {
            $rapport = $this->runner->executer();
            ++$n;
            if ($rapport->verrouille && !$watch) {
                $io->warning($rapport->resume());

                return Command::SUCCESS;
            }
            if ($io->isVerbose() || !$watch) {
                $io->writeln(sprintf('[%s] %s', date('H:i:s'), $rapport->resume()));
            }
            $this->em->clear();
            if (!$watch || $this->arret || ($iterations > 0 && $n >= $iterations)) {
                break;
            }
            sleep($intervalle);
        } while (!$this->arret);

        if ($watch) {
            $io->success(sprintf('Worker infirmerie arrêté après %d passage(s).', $n));
        }

        return Command::SUCCESS;
    }
}
