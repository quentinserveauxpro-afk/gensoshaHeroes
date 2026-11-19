<?php

namespace App\Entity;

use App\Enum\Statistique;
use App\Repository\BonusRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** Définition générale d'un bonus ; l'attribution à un héros passe par HeroBonus. */
#[ORM\Entity(repositoryClass: BonusRepository::class)]
#[UniqueEntity('code')]
class Bonus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    private ?string $code = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: Statistique::class, length: 20)]
    private Statistique $statistique = Statistique::GLOBAL;

    #[ORM\Column]
    #[Assert\Range(min: -50, max: 50)]
    private int $valeurParDefaut = 5;

    public function getId(): ?int { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
    public function getStatistique(): Statistique { return $this->statistique; }
    public function setStatistique(Statistique $s): static { $this->statistique = $s; return $this; }
    public function getValeurParDefaut(): int { return $this->valeurParDefaut; }
    public function setValeurParDefaut(int $v): static { $this->valeurParDefaut = $v; return $this; }

    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
