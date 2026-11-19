<?php

namespace App\Entity;

use App\Repository\HeroRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeroRepository::class)]
class Hero
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $rang = null;

    #[ORM\Column]
    private ?int $pointsDeVie = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $statCombat = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $statIntelligence = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $statCharisme = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $statMobilite = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $statVigueur = null;

    #[ORM\Column]
    private ?float $positionX = null;

    #[ORM\Column]
    private ?float $positionY = null;

    #[ORM\Column]
    private ?int $niveau = null;

    #[ORM\Column]
    private ?int $xp = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getRang(): ?string
    {
        return $this->rang;
    }

    public function setRang(string $rang): static
    {
        $this->rang = $rang;

        return $this;
    }

    public function getPointsDeVie(): ?int
    {
        return $this->pointsDeVie;
    }

    public function setPointsDeVie(int $pointsDeVie): static
    {
        $this->pointsDeVie = $pointsDeVie;

        return $this;
    }

    public function getStatCombat(): ?string
    {
        return $this->statCombat;
    }

    public function setStatCombat(string $statCombat): static
    {
        $this->statCombat = $statCombat;

        return $this;
    }

    public function getStatIntelligence(): ?string
    {
        return $this->statIntelligence;
    }

    public function setStatIntelligence(string $statIntelligence): static
    {
        $this->statIntelligence = $statIntelligence;

        return $this;
    }

    public function getStatCharisme(): ?string
    {
        return $this->statCharisme;
    }

    public function setStatCharisme(string $statCharisme): static
    {
        $this->statCharisme = $statCharisme;

        return $this;
    }

    public function getStatMobilite(): ?string
    {
        return $this->statMobilite;
    }

    public function setStatMobilite(string $statMobilite): static
    {
        $this->statMobilite = $statMobilite;

        return $this;
    }

    public function getStatVigueur(): ?string
    {
        return $this->statVigueur;
    }

    public function setStatVigueur(string $statVigueur): static
    {
        $this->statVigueur = $statVigueur;

        return $this;
    }

    public function getPositionX(): ?float
    {
        return $this->positionX;
    }

    public function setPositionX(float $positionX): static
    {
        $this->positionX = $positionX;

        return $this;
    }

    public function getPositionY(): ?float
    {
        return $this->positionY;
    }

    public function setPositionY(float $positionY): static
    {
        $this->positionY = $positionY;

        return $this;
    }

    public function getNiveau(): ?int
    {
        return $this->niveau;
    }

    public function setNiveau(int $niveau): static
    {
        $this->niveau = $niveau;

        return $this;
    }

    public function getXp(): ?int
    {
        return $this->xp;
    }

    public function setXp(int $xp): static
    {
        $this->xp = $xp;

        return $this;
    }
}
