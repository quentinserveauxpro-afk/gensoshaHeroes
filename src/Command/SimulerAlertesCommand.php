<?php

namespace App\Command;

use App\Entity\RequeteCitoyenne;
use App\Enum\NiveauUrgence;
use App\Enum\OrigineAlerte;
use App\Enum\RequeteCategorie;
use App\Service\Requete\RequeteCitoyenneService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Simulation aléatoire de requêtes citoyennes — environnement de développement uniquement.
 * Les alertes générées passent par le flux métier normal (RequeteCitoyenneService) et sont persistées.
 */
#[When(env: 'dev')]
#[AsCommand(name: 'app:alertes:simuler', description: '[DEV] Génère des requêtes citoyennes aléatoires (et leurs alertes)')]
final class SimulerAlertesCommand
{
    private const SCENARIOS = [
        ['Anomalie énergétique au port', 'Panache d\'énergie violette au-dessus du quai 4.', 'Quartier Portuaire • Quai 4'],
        ['Glissement de terrain', 'Route coupée, deux véhicules ensevelis.', 'Colline des Chênes'],
        ['Individu télékinésiste en centre-ville', 'Voitures soulevées place centrale.', 'Place de l\'Hôtel de Ville'],
        ['Incendie dans un entrepôt', 'Fumées toxiques signalées.', 'Entrepôts Nord'],
    ];

    public function __construct(private readonly RequeteCitoyenneService $service)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Nombre de requêtes')] int $nombre = 3): int
    {
        for ($i = 0; $i < $nombre; ++$i) {
            [$titre, $description, $adresse] = self::SCENARIOS[array_rand(self::SCENARIOS)];
            $urgences = NiveauUrgence::cases();
            $requete = (new RequeteCitoyenne())
                ->setTitre($titre)->setDescription($description)->setAdresse($adresse)
                ->setPositionX(random_int(5, 95))->setPositionY(random_int(5, 95))
                ->setNiveauUrgence($urgences[array_rand($urgences)])
                ->setCategorie(RequeteCategorie::cases()[array_rand(RequeteCategorie::cases())])
                ->setOrigine(OrigineAlerte::cases()[array_rand(OrigineAlerte::cases())])
                ->setNomDemandeur('Simulation');
            $this->service->enregistrer($requete);
            $io->writeln(sprintf('%s — %s (%s)', $requete->getReference(), $titre, $requete->getNiveauUrgence()->label()));
        }
        $io->success(sprintf('%d requête(s) simulée(s).', $nombre));

        return 0;
    }
}
