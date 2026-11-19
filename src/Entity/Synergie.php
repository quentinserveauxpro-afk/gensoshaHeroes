<?php

namespace App\Entity;

use App\Enum\Difficulte;
use App\Enum\Statistique;
use App\Repository\SynergieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Synergie entre plusieurs héros : lorsqu'au moins `nombreMinimalHeros` héros concernés
 * sont dans la même équipe (et que la mission atteint `difficulteMinimale` si renseignée),
 * la statistique ciblée de l'équipe reçoit `valeurBonus`.
 */
#[ORM\Entity(repositoryClass: SynergieRepository::class)]
class Synergie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, Hero> */
    #[ORM\ManyToMany(targetEntity: Hero::class, inversedBy: 'synergies')]
    #[ORM\JoinTable(name: 'synergie_hero')]
    #[Assert\Count(min: 2)]
    private Collection $heroes;

    #[ORM\Column(enumType: Statistique::class, length: 20)]
    private Statistique $statistique = Statistique::GLOBAL;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 50)]
    private int $valeurBonus = 5;

    /** Condition : nombre minimal de héros concernés présents (null = tous). */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $nombreMinimalHeros = null;

    /** Condition : rang minimal de la mission (null = toutes). */
    #[ORM\Column(enumType: Difficulte::class, length: 1, nullable: true)]
    private ?Difficulte $difficulteMinimale = null;

    #[ORM\Column]
    private bool $actif = true;

    public function __construct()
    {
        $this->heroes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }

    /** @return Collection<int, Hero> */
    public function getHeroes(): Collection { return $this->heroes; }

    public function addHero(Hero $hero): static
    {
        if (!$this->heroes->contains($hero)) {
            $this->heroes->add($hero);
        }
        if (!$hero->getSynergies()->contains($this)) {
            $hero->getSynergies()->add($this);
        }

        return $this;
    }

    public function removeHero(Hero $hero): static
    {
        $this->heroes->removeElement($hero);
        $hero->getSynergies()->removeElement($this);

        return $this;
    }

    public function getStatistique(): Statistique { return $this->statistique; }
    public function setStatistique(Statistique $s): static { $this->statistique = $s; return $this; }
    public function getValeurBonus(): int { return $this->valeurBonus; }
    public function setValeurBonus(int $v): static { $this->valeurBonus = $v; return $this; }
    public function getNombreMinimalHeros(): ?int { return $this->nombreMinimalHeros; }
    public function setNombreMinimalHeros(?int $n): static { $this->nombreMinimalHeros = $n; return $this; }
    public function getDifficulteMinimale(): ?Difficulte { return $this->difficulteMinimale; }
    public function setDifficulteMinimale(?Difficulte $d): static { $this->difficulteMinimale = $d; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $a): static { $this->actif = $a; return $this; }
}
