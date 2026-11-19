<?php

namespace App\Entity;

use App\Enum\NiveauUrgence;
use App\Enum\OrigineAlerte;
use App\Enum\RequeteCategorie;
use App\Enum\RequeteStatut;
use App\Repository\RequeteCitoyenneRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RequeteCitoyenneRepository::class)]
class RequeteCitoyenne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $adresse = null;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private float $positionX = 50.0;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private float $positionY = 50.0;

    #[ORM\Column(enumType: NiveauUrgence::class, length: 20)]
    private NiveauUrgence $niveauUrgence = NiveauUrgence::MOYENNE;

    #[ORM\Column(enumType: RequeteStatut::class, length: 20)]
    private RequeteStatut $statut = RequeteStatut::NOUVELLE;

    #[ORM\Column(enumType: RequeteCategorie::class, length: 30)]
    private RequeteCategorie $categorie = RequeteCategorie::ASSISTANCE_PUBLIQUE;

    #[ORM\Column(enumType: OrigineAlerte::class, length: 20)]
    private OrigineAlerte $origine = OrigineAlerte::CIVIL;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nomDemandeur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Mission $mission = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $d): static { $this->description = $d; return $this; }
    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(string $a): static { $this->adresse = $a; return $this; }
    public function getPositionX(): float { return $this->positionX; }
    public function setPositionX(float $x): static { $this->positionX = $x; return $this; }
    public function getPositionY(): float { return $this->positionY; }
    public function setPositionY(float $y): static { $this->positionY = $y; return $this; }
    public function getNiveauUrgence(): NiveauUrgence { return $this->niveauUrgence; }
    public function setNiveauUrgence(NiveauUrgence $n): static { $this->niveauUrgence = $n; return $this; }
    public function getStatut(): RequeteStatut { return $this->statut; }
    public function setStatut(RequeteStatut $s): static { $this->statut = $s; return $this; }
    public function getCategorie(): RequeteCategorie { return $this->categorie; }
    public function setCategorie(RequeteCategorie $c): static { $this->categorie = $c; return $this; }
    public function getOrigine(): OrigineAlerte { return $this->origine; }
    public function setOrigine(OrigineAlerte $o): static { $this->origine = $o; return $this; }
    public function getNomDemandeur(): ?string { return $this->nomDemandeur; }
    public function setNomDemandeur(string $n): static { $this->nomDemandeur = $n; return $this; }
    public function getDateCreation(): ?\DateTimeImmutable { return $this->dateCreation; }
    public function setDateCreation(\DateTimeImmutable $d): static { $this->dateCreation = $d; return $this; }
    public function getMission(): ?Mission { return $this->mission; }
    public function setMission(?Mission $mission): static { $this->mission = $mission; return $this; }

    public function getReference(): string
    {
        return sprintf('REQ-%04d', (int) $this->id);
    }
}
