<?php

namespace App\Requete;

use App\Entity\PieceJointe;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Stockage sécurisé des pièces jointes des requêtes citoyennes.
 *
 * - liste blanche : JPEG, PNG, WebP, PDF (type MIME détecté sur le contenu ET extension) ;
 * - 5 Mo maximum ;
 * - répertoire hors de public/ (var/uploads/requetes) ; nom aléatoire, jamais celui du client ;
 * - téléchargement réservé aux opérateurs autorisés, en pièce jointe (jamais exécuté).
 */
final class PieceJointeStorage
{
    public const TAILLE_MAX = '5M';
    /** @var array<string, string> type MIME → extension */
    public const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/uploads/requetes')]
        private readonly string $repertoire,
        private readonly ClockInterface $clock,
    ) {
    }

    public function stocker(UploadedFile $fichier): PieceJointe
    {
        $type = (string) $fichier->getMimeType();
        $extensionClient = strtolower($fichier->getClientOriginalExtension());
        if (!isset(self::TYPES[$type]) || !\in_array($extensionClient, self::EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Type de fichier non autorisé.');
        }

        (new Filesystem())->mkdir($this->repertoire, 0750);
        $nom = bin2hex(random_bytes(16)).'.'.self::TYPES[$type];
        $empreinte = (string) hash_file('sha256', $fichier->getPathname());
        $taille = (int) $fichier->getSize();
        $origine = (new AsciiSlugger())->slug(pathinfo($fichier->getClientOriginalName(), \PATHINFO_FILENAME))->lower()->truncate(100)->toString();
        $fichier->move($this->repertoire, $nom);

        return new PieceJointe(($origine ?: 'piece-jointe').'.'.self::TYPES[$type], $nom, $type, $taille, $empreinte, $this->clock->now());
    }

    public function chemin(PieceJointe $pieceJointe): string
    {
        $nom = basename($pieceJointe->getNomStockage());

        return $this->repertoire.'/'.$nom;
    }
}
