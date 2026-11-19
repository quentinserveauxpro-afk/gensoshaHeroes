<?php

namespace App\Entity;

use App\Repository\ObjectifMissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ObjectifMissionRepository::class)]
class ObjectifMission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $progression = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $ordreAffichage = 0;

    #[ORM\Column]
    private bool $obligatoire = true;

    #[ORM\Column]
    private bool $termine = false;

    #[ORM\ManyToOne(inversedBy: 'objectifs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Mission $mission = null;

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getProgression(): int { return $this->progression; }

    public function setProgression(int $progression): static
    {
        $this->progression = max(0, min(100, $progression));
        if (100 === $this->progression) {
            $this->termine = true;
        }

        return $this;
    }

    public function getOrdreAffichage(): int { return $this->ordreAffichage; }
    public function setOrdreAffichage(int $ordreAffichage): static { $this->ordreAffichage = $ordreAffichage; return $this; }
    public function isObligatoire(): bool { return $this->obligatoire; }
    public function setObligatoire(bool $obligatoire): static { $this->obligatoire = $obligatoire; return $this; }
    public function isTermine(): bool { return $this->termine; }

    public function setTermine(bool $termine): static
    {
        $this->termine = $termine;
        if ($termine) {
            $this->progression = 100;
        }

        return $this;
    }

    public function getMission(): ?Mission { return $this->mission; }
    public function setMission(?Mission $mission): static { $this->mission = $mission; return $this; }

    /** État d'affichage : termine, en_cours, en_attente. */
    public function getEtat(): string
    {
        return match (true) {
            $this->termine => 'termine',
            $this->progression > 0 => 'en_cours',
            default => 'en_attente',
        };
    }
}
