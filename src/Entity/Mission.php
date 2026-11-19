<?php

namespace App\Entity;

use App\Repository\MissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MissionRepository::class)]
class Mission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $niveauDeDifficulte = null;

    #[ORM\Column]
    private ?int $tauxDeReussite = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $timer = null;

    #[ORM\Column(nullable: true)]
    private ?int $combatRequis = null;

    #[ORM\Column(nullable: true)]
    private ?int $intelligenceRequis = null;

    #[ORM\Column(nullable: true)]
    private ?int $charismeRequis = null;

    #[ORM\Column(nullable: true)]
    private ?int $mobiliteRequis = null;

    #[ORM\Column(nullable: true)]
    private ?int $vigueurRequis = null;

    /**
     * @var Collection<int, Hero>
     */
    #[ORM\OneToMany(targetEntity: Hero::class, mappedBy: 'ManyToOne')]
    private Collection $heroes;

    public function __construct()
    {
        $this->heroes = new ArrayCollection();
    }

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getNiveauDeDifficulte(): ?string
    {
        return $this->niveauDeDifficulte;
    }

    public function setNiveauDeDifficulte(string $niveauDeDifficulte): static
    {
        $this->niveauDeDifficulte = $niveauDeDifficulte;

        return $this;
    }

    public function getTauxDeReussite(): ?int
    {
        return $this->tauxDeReussite;
    }

    public function setTauxDeReussite(int $tauxDeReussite): static
    {
        $this->tauxDeReussite = $tauxDeReussite;

        return $this;
    }

    public function getTimer(): ?\DateTimeImmutable
    {
        return $this->timer;
    }

    public function setTimer(\DateTimeImmutable $timer): static
    {
        $this->timer = $timer;

        return $this;
    }

    public function getCombatRequis(): ?int
    {
        return $this->combatRequis;
    }

    public function setCombatRequis(?int $combatRequis): static
    {
        $this->combatRequis = $combatRequis;

        return $this;
    }

    public function getIntelligenceRequis(): ?int
    {
        return $this->intelligenceRequis;
    }

    public function setIntelligenceRequis(?int $intelligenceRequis): static
    {
        $this->intelligenceRequis = $intelligenceRequis;

        return $this;
    }

    public function getCharismeRequis(): ?int
    {
        return $this->charismeRequis;
    }

    public function setCharismeRequis(int $charismeRequis): static
    {
        $this->charismeRequis = $charismeRequis;

        return $this;
    }

    public function getMobiliteRequis(): ?int
    {
        return $this->mobiliteRequis;
    }

    public function setMobiliteRequis(?int $mobiliteRequis): static
    {
        $this->mobiliteRequis = $mobiliteRequis;

        return $this;
    }

    public function getVigueurRequis(): ?int
    {
        return $this->vigueurRequis;
    }

    public function setVigueurRequis(?int $vigueurRequis): static
    {
        $this->vigueurRequis = $vigueurRequis;

        return $this;
    }

    /**
     * @return Collection<int, Hero>
     */
    public function getHeroes(): Collection
    {
        return $this->heroes;
    }

    public function addHero(Hero $hero): static
    {
        if (!$this->heroes->contains($hero)) {
            $this->heroes->add($hero);
            $hero->setManyToOne($this);
        }

        return $this;
    }

    public function removeHero(Hero $hero): static
    {
        if ($this->heroes->removeElement($hero)) {
            // set the owning side to null (unless already changed)
            if ($hero->getManyToOne() === $this) {
                $hero->setManyToOne(null);
            }
        }

        return $this;
    }
}
