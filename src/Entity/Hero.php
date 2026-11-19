<?php

namespace App\Entity;

use App\Repository\HeroRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\HeroRole;
use App\Enum\HeroStatut;
use App\Enum\Statistique;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: HeroRepository::class)]
#[ORM\HasLifecycleCallbacks]
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

    #[ORM\ManyToOne(inversedBy: 'heroes')]
    private ?Mission $missions = null;

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

    public function getManyToOne(): ?Mission
    {
        return $this->missions;
    }

    public function setManyToOne(?Mission $missions): static
    {
        $this->missions = $missions;

        return $this;
    }

    // ------------------------------------------------------------------
    // Ajouts (additifs) — l'expérience utilise la colonne existante $xp,
    // les statistiques utilisent les colonnes existantes stat* (DECIMAL).
    // ------------------------------------------------------------------

    use TimestampableTrait;

    public const STAT_MIN = 0;
    public const STAT_MAX = 100;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Identité civile (ex. « Marcus Vane »). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alias = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $specialite = null;

    #[ORM\Column(enumType: HeroRole::class, length: 20)]
    private HeroRole $role = HeroRole::ASSAUT;

    #[ORM\Column(enumType: HeroStatut::class, length: 20)]
    private HeroStatut $statut = HeroStatut::DISPONIBLE;

    #[ORM\Column]
    #[Assert\Positive]
    private int $pointsDeVieMaximum = 100;

    /** Indice de fatigue de 0 à 100. */
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $fatigue = 0;

    /** Libellé de la position courante (ex. « Secteur QG-Genosha »). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $secteur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** @var Collection<int, Aptitude> */
    #[ORM\ManyToMany(targetEntity: Aptitude::class)]
    #[ORM\JoinTable(name: 'hero_aptitude')]
    private Collection $aptitudes;

    /** @var Collection<int, HeroBonus> */
    #[ORM\OneToMany(targetEntity: HeroBonus::class, mappedBy: 'hero', cascade: ['persist'], orphanRemoval: true)]
    private Collection $heroBonus;

    /** @var Collection<int, AffectationMission> */
    #[ORM\OneToMany(targetEntity: AffectationMission::class, mappedBy: 'hero')]
    #[ORM\OrderBy(['dateAffectation' => 'DESC'])]
    private Collection $affectations;

    /** @var Collection<int, Synergie> */
    #[ORM\ManyToMany(targetEntity: Synergie::class, mappedBy: 'heroes')]
    private Collection $synergies;

    public function __construct()
    {
        $this->aptitudes = new ArrayCollection();
        $this->heroBonus = new ArrayCollection();
        $this->affectations = new ArrayCollection();
        $this->synergies = new ArrayCollection();
    }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getAlias(): ?string { return $this->alias; }
    public function setAlias(?string $alias): static { $this->alias = $alias; return $this; }
    public function getSpecialite(): ?string { return $this->specialite; }
    public function setSpecialite(?string $specialite): static { $this->specialite = $specialite; return $this; }
    public function getRole(): HeroRole { return $this->role; }
    public function setRole(HeroRole $role): static { $this->role = $role; return $this; }
    public function getStatut(): HeroStatut { return $this->statut; }
    public function setStatut(HeroStatut $statut): static { $this->statut = $statut; return $this; }
    public function getPointsDeVieMaximum(): int { return $this->pointsDeVieMaximum; }
    public function setPointsDeVieMaximum(int $max): static { $this->pointsDeVieMaximum = $max; return $this; }
    public function getFatigue(): int { return $this->fatigue; }
    public function setFatigue(int $fatigue): static { $this->fatigue = max(0, min(100, $fatigue)); return $this; }
    public function getSecteur(): ?string { return $this->secteur; }
    public function setSecteur(?string $secteur): static { $this->secteur = $secteur; return $this; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    // --- Statistiques entières (0-100) adossées aux colonnes DECIMAL existantes ---

    #[Assert\Range(min: self::STAT_MIN, max: self::STAT_MAX)]
    public function getCombat(): int { return self::versEntier($this->statCombat); }
    public function setCombat(int $v): static { $this->statCombat = (string) $v; return $this; }

    #[Assert\Range(min: self::STAT_MIN, max: self::STAT_MAX)]
    public function getIntelligence(): int { return self::versEntier($this->statIntelligence); }
    public function setIntelligence(int $v): static { $this->statIntelligence = (string) $v; return $this; }

    #[Assert\Range(min: self::STAT_MIN, max: self::STAT_MAX)]
    public function getCharisme(): int { return self::versEntier($this->statCharisme); }
    public function setCharisme(int $v): static { $this->statCharisme = (string) $v; return $this; }

    #[Assert\Range(min: self::STAT_MIN, max: self::STAT_MAX)]
    public function getMobilite(): int { return self::versEntier($this->statMobilite); }
    public function setMobilite(int $v): static { $this->statMobilite = (string) $v; return $this; }

    #[Assert\Range(min: self::STAT_MIN, max: self::STAT_MAX)]
    public function getVigueur(): int { return self::versEntier($this->statVigueur); }
    public function setVigueur(int $v): static { $this->statVigueur = (string) $v; return $this; }

    public function getStat(Statistique $statistique): int
    {
        return match ($statistique) {
            Statistique::COMBAT => $this->getCombat(),
            Statistique::INTELLIGENCE => $this->getIntelligence(),
            Statistique::CHARISME => $this->getCharisme(),
            Statistique::MOBILITE => $this->getMobilite(),
            Statistique::VIGUEUR => $this->getVigueur(),
            Statistique::GLOBAL => (int) round(array_sum($this->getStats()) / 5),
        };
    }

    public function setStat(Statistique $statistique, int $valeur): static
    {
        return match ($statistique) {
            Statistique::COMBAT => $this->setCombat($valeur),
            Statistique::INTELLIGENCE => $this->setIntelligence($valeur),
            Statistique::CHARISME => $this->setCharisme($valeur),
            Statistique::MOBILITE => $this->setMobilite($valeur),
            Statistique::VIGUEUR => $this->setVigueur($valeur),
            Statistique::GLOBAL => throw new \InvalidArgumentException('La statistique GLOBAL est calculée.'),
        };
    }

    /** @return array<string, int> clé = valeur de l'enum Statistique */
    public function getStats(): array
    {
        return [
            Statistique::COMBAT->value => $this->getCombat(),
            Statistique::INTELLIGENCE->value => $this->getIntelligence(),
            Statistique::CHARISME->value => $this->getCharisme(),
            Statistique::MOBILITE->value => $this->getMobilite(),
            Statistique::VIGUEUR->value => $this->getVigueur(),
        ];
    }

    /** Santé en pourcentage (0-100). */
    public function getSantePourcentage(): int
    {
        if ($this->pointsDeVieMaximum <= 0) {
            return 0;
        }

        return (int) round(max(0, min(1, ((int) $this->pointsDeVie) / $this->pointsDeVieMaximum)) * 100);
    }

    public function isDisponible(): bool
    {
        return HeroStatut::DISPONIBLE === $this->statut;
    }

    /** Initiales affichées lorsqu'aucun portrait n'est disponible. */
    public function getInitiales(): string
    {
        return mb_strtoupper(mb_substr((string) $this->nom, 0, 2));
    }

    #[Assert\Callback]
    public function validerPointsDeVie(ExecutionContextInterface $context): void
    {
        if (null === $this->pointsDeVie) {
            $context->buildViolation('Les points de vie sont obligatoires.')->atPath('pointsDeVie')->addViolation();

            return;
        }
        if ($this->pointsDeVie < 0) {
            $context->buildViolation('Les points de vie ne peuvent pas être négatifs.')->atPath('pointsDeVie')->addViolation();
        }
        if ($this->pointsDeVie > $this->pointsDeVieMaximum) {
            $context->buildViolation('Les points de vie ne peuvent pas dépasser le maximum ({{ max }}).')
                ->setParameter('{{ max }}', (string) $this->pointsDeVieMaximum)
                ->atPath('pointsDeVie')->addViolation();
        }
        if (null !== $this->niveau && $this->niveau < 1) {
            $context->buildViolation('Le niveau minimal est 1.')->atPath('niveau')->addViolation();
        }
        if (null !== $this->xp && $this->xp < 0) {
            $context->buildViolation('L\'expérience ne peut pas être négative.')->atPath('xp')->addViolation();
        }
    }

    /** @return Collection<int, Aptitude> */
    public function getAptitudes(): Collection { return $this->aptitudes; }

    public function addAptitude(Aptitude $aptitude): static
    {
        if (!$this->aptitudes->contains($aptitude)) {
            $this->aptitudes->add($aptitude);
        }

        return $this;
    }

    public function removeAptitude(Aptitude $aptitude): static
    {
        $this->aptitudes->removeElement($aptitude);

        return $this;
    }

    /** @return Collection<int, HeroBonus> */
    public function getHeroBonus(): Collection { return $this->heroBonus; }

    public function addHeroBonus(HeroBonus $heroBonus): static
    {
        if (!$this->heroBonus->contains($heroBonus)) {
            $this->heroBonus->add($heroBonus);
            $heroBonus->setHero($this);
        }

        return $this;
    }

    public function removeHeroBonus(HeroBonus $heroBonus): static
    {
        if ($this->heroBonus->removeElement($heroBonus) && $heroBonus->getHero() === $this) {
            $heroBonus->setHero(null);
        }

        return $this;
    }

    /** @return list<HeroBonus> */
    public function getBonusEnVigueur(\DateTimeImmutable $now): array
    {
        return array_values($this->heroBonus->filter(static fn (HeroBonus $b) => $b->isEnVigueur($now))->toArray());
    }

    /** @return Collection<int, AffectationMission> */
    public function getAffectations(): Collection { return $this->affectations; }

    public function addAffectation(AffectationMission $affectation): static
    {
        if (!$this->affectations->contains($affectation)) {
            $this->affectations->add($affectation);
            $affectation->setHero($this);
        }

        return $this;
    }

    public function getAffectationActive(): ?AffectationMission
    {
        foreach ($this->affectations as $affectation) {
            if ($affectation->isActive() && $affectation->getMission()?->getStatut()->isActive()) {
                return $affectation;
            }
        }

        return null;
    }

    /** @return Collection<int, Synergie> */
    public function getSynergies(): Collection { return $this->synergies; }

    public function __toString(): string
    {
        return (string) $this->nom;
    }

    private static function versEntier(?string $decimal): int
    {
        return (int) round((float) $decimal);
    }
}
