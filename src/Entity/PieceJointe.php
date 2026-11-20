<?php

namespace App\Entity;

use App\Repository\PieceJointeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fichier joint à une requête citoyenne.
 *
 * Le fichier est stocké hors du répertoire public, sous un nom aléatoire ; il n'est servi
 * qu'aux opérateurs autorisés, en téléchargement (jamais exécuté ni affiché en ligne).
 */
#[ORM\Entity(repositoryClass: PieceJointeRepository::class)]
class PieceJointe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Nom d'origine nettoyé (affichage uniquement). */
    #[ORM\Column(length: 150)]
    private string $nomOriginal;

    /** Nom de stockage aléatoire (hexadécimal + extension issue de la liste blanche). */
    #[ORM\Column(length: 80, unique: true)]
    private string $nomStockage;

    #[ORM\Column(length: 100)]
    private string $typeMime;

    #[ORM\Column]
    private int $taille;

    #[ORM\Column(length: 64)]
    private string $empreinteSha256;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $nomOriginal, string $nomStockage, string $typeMime, int $taille, string $empreinteSha256, \DateTimeImmutable $createdAt)
    {
        $this->nomOriginal = $nomOriginal;
        $this->nomStockage = $nomStockage;
        $this->typeMime = $typeMime;
        $this->taille = $taille;
        $this->empreinteSha256 = $empreinteSha256;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getNomOriginal(): string { return $this->nomOriginal; }
    public function getNomStockage(): string { return $this->nomStockage; }
    public function getTypeMime(): string { return $this->typeMime; }
    public function getTaille(): int { return $this->taille; }
    public function getEmpreinteSha256(): string { return $this->empreinteSha256; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
