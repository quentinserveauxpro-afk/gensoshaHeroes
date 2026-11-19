<?php

namespace App\Entity;

use App\Enum\VillainStatut;
use App\Repository\VillainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VillainRepository::class)]
class Villain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Niveau de menace de 1 (nuisance) à 10 (cataclysmique). */
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 10)]
    private int $niveauMenace = 1;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $combat = 0;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $intelligence = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(enumType: VillainStatut::class, length: 20)]
    private VillainStatut $statut = VillainStatut::EN_LIBERTE;

    /** @var Collection<int, Mission> */
    #[ORM\ManyToMany(targetEntity: Mission::class, mappedBy: 'villains')]
    private Collection $missions;

    public function __construct()
    {
        $this->missions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getNiveauMenace(): int { return $this->niveauMenace; }
    public function setNiveauMenace(int $n): static { $this->niveauMenace = $n; return $this; }
    public function getCombat(): int { return $this->combat; }
    public function setCombat(int $combat): static { $this->combat = $combat; return $this; }
    public function getIntelligence(): int { return $this->intelligence; }
    public function setIntelligence(int $intelligence): static { $this->intelligence = $intelligence; return $this; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }
    public function getStatut(): VillainStatut { return $this->statut; }
    public function setStatut(VillainStatut $statut): static { $this->statut = $statut; return $this; }

    /** @return Collection<int, Mission> */
    public function getMissions(): Collection { return $this->missions; }

    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
