<?php

namespace App\Entity;

use App\Enum\NiveauAlerte;
use App\Repository\AlerteRepository;
use Doctrine\ORM\Mapping as ORM;

/** Alerte persistée, générée à partir d'événements métier (requête urgente, mission critique). */
#[ORM\Entity(repositoryClass: AlerteRepository::class)]
#[ORM\Index(columns: ['acquittee', 'created_at'], name: 'idx_alerte_active')]
class Alerte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(enumType: NiveauAlerte::class, length: 20)]
    private NiveauAlerte $niveau;

    #[ORM\Column(length: 40)]
    private string $code;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private bool $acquittee = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Mission $mission = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RequeteCitoyenne $requete = null;

    public function __construct(string $titre, string $message, NiveauAlerte $niveau, string $code, ?\DateTimeImmutable $createdAt = null)
    {
        $this->titre = $titre;
        $this->message = $message;
        $this->niveau = $niveau;
        $this->code = $code;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): string { return $this->titre; }
    public function getMessage(): string { return $this->message; }
    public function getNiveau(): NiveauAlerte { return $this->niveau; }
    public function getCode(): string { return $this->code; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isAcquittee(): bool { return $this->acquittee; }
    public function acquitter(): static { $this->acquittee = true; return $this; }
    public function getMission(): ?Mission { return $this->mission; }
    public function setMission(?Mission $m): static { $this->mission = $m; return $this; }
    public function getRequete(): ?RequeteCitoyenne { return $this->requete; }
    public function setRequete(?RequeteCitoyenne $r): static { $this->requete = $r; return $this; }
}
