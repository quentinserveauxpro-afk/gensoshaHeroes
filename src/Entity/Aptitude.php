<?php

namespace App\Entity;

use App\Repository\AptitudeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Aptitude ou immunité (vol, immunité énergétique, piratage…) possédée par un héros
 * et pouvant être exigée par une mission.
 */
#[ORM\Entity(repositoryClass: AptitudeRepository::class)]
#[UniqueEntity('code')]
class Aptitude
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[A-Z0-9_]+$/')]
    private ?string $code = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function __construct(?string $code = null, ?string $nom = null)
    {
        $this->code = $code;
        $this->nom = $nom;
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
