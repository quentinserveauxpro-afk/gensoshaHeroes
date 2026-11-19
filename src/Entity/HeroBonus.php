<?php

namespace App\Entity;

use App\Enum\BonusOrigine;
use App\Repository\HeroBonusRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HeroBonusRepository::class)]
class HeroBonus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'heroBonus')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Hero $hero = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Bonus $bonus = null;

    #[ORM\Column]
    #[Assert\Range(min: -50, max: 50)]
    private int $valeur = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThan(propertyPath: 'dateDebut')]
    private ?\DateTimeImmutable $dateExpiration = null;

    #[ORM\Column(enumType: BonusOrigine::class, length: 20)]
    private BonusOrigine $origine = BonusOrigine::EQUIPEMENT;

    #[ORM\Column]
    private bool $cumulable = true;

    #[ORM\Column]
    private bool $actif = true;

    public function __construct(?Bonus $bonus = null, ?int $valeur = null)
    {
        $this->bonus = $bonus;
        $this->valeur = $valeur ?? $bonus?->getValeurParDefaut() ?? 0;
        $this->dateDebut = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getHero(): ?Hero { return $this->hero; }
    public function setHero(?Hero $hero): static { $this->hero = $hero; return $this; }
    public function getBonus(): ?Bonus { return $this->bonus; }
    public function setBonus(Bonus $bonus): static { $this->bonus = $bonus; return $this; }
    public function getValeur(): int { return $this->valeur; }
    public function setValeur(int $valeur): static { $this->valeur = $valeur; return $this; }
    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(\DateTimeImmutable $d): static { $this->dateDebut = $d; return $this; }
    public function getDateExpiration(): ?\DateTimeImmutable { return $this->dateExpiration; }
    public function setDateExpiration(?\DateTimeImmutable $d): static { $this->dateExpiration = $d; return $this; }
    public function getOrigine(): BonusOrigine { return $this->origine; }
    public function setOrigine(BonusOrigine $o): static { $this->origine = $o; return $this; }
    public function isCumulable(): bool { return $this->cumulable; }
    public function setCumulable(bool $c): static { $this->cumulable = $c; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $a): static { $this->actif = $a; return $this; }

    /** Le bonus est-il applicable à l'instant donné ? */
    public function isEnVigueur(\DateTimeImmutable $now): bool
    {
        return $this->actif
            && null !== $this->dateDebut && $this->dateDebut <= $now
            && (null === $this->dateExpiration || $this->dateExpiration > $now);
    }
}
