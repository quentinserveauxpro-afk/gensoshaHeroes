<?php

namespace App\Command;

use App\GameTime\GameTimeNormalizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Met en conformité les trajets et soins en cours avec l'échelle temporelle accélérée
 * (30 s à 10 min de trajet, 1 à 15 min de soin) sans rien supprimer ni rallonger.
 *
 *   php bin/console app:game-time:normalize --dry-run   # aperçu, aucune écriture
 *   php bin/console app:game-time:normalize             # application
 */
#[AsCommand(name: 'app:game-time:normalize', description: 'Raccourcit les trajets et soins en cours selon l\'échelle temporelle accélérée')]
final class GameTimeNormalizeCommand
{
    public function __construct(private readonly GameTimeNormalizer $normalizer)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Simulation : affiche les changements sans rien modifier')] bool $dryRun = false,
    ): int {
        $lignes = $this->normalizer->normaliser(!$dryRun);
        $format = static fn (?\DateTimeImmutable $d) => $d?->format('Y-m-d H:i:s') ?? '—';
        if ([] === $lignes) {
            $io->success('Aucun trajet ni soin en cours : rien à normaliser.');

            return Command::SUCCESS;
        }
        $io->table(['Opération', 'Id', 'Héros', 'Fin avant', 'Fin après', 'Action'], array_map(
            static fn (array $l) => [$l['type'], $l['id'], $l['hero'], $format($l['avant']), $format($l['apres']), $l['action']],
            $lignes,
        ));
        $modifiees = \count(array_filter($lignes, static fn (array $l) => 'inchangé' !== $l['action']));
        $dryRun
            ? $io->note(sprintf('Simulation : %d opération(s) seraient modifiées. Aucune donnée n\'a été écrite.', $modifiees))
            : $io->success(sprintf('%d opération(s) normalisée(s) sur %d.', $modifiees, \count($lignes)));

        return Command::SUCCESS;
    }
}
