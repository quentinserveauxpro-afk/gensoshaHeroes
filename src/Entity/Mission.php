<?php

namespace App\Entity;

use App\Repository\MissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Difficulte;
use App\Enum\MissionStatut;
use App\Enum\NiveauUrgence;
use App\Enum\Statistique;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: MissionRepository::class)]
#[ORM\HasLifecycleCallbacks]
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

    // ------------------------------------------------------------------
    // Ajouts (additifs).
    // - $nom tient lieu de « titre » ; $timer tient lieu de « dateFinPrevue »
    //   (datetime_immutable) ; $niveauDeDifficulte stocke la valeur de l'enum Difficulte ;
    //   $tauxDeReussite tient lieu de « tauxReussite ».
    // - Le constructeur existant n'étant pas modifiable, les nouvelles collections
    //   sont initialisées à la demande (Doctrine les remplace par des PersistentCollection
    //   à l'hydratation).
    // - La relation existante $heroes n'est pas utilisée : l'affectation passe par
    //   AffectationMission (voir README / rapport : mapping existant incohérent).
    // ------------------------------------------------------------------

    use TimestampableTrait;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $localisation = null;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private float $positionX = 50.0;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private float $positionY = 50.0;

    #[ORM\Column(enumType: MissionStatut::class, length: 20)]
    private MissionStatut $statut = MissionStatut::BROUILLON;

    #[ORM\Column(enumType: NiveauUrgence::class, length: 20)]
    private NiveauUrgence $niveauUrgence = NiveauUrgence::MOYENNE;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 12)]
    private int $tailleEquipeMinimale = 1;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 12)]
    private int $tailleEquipeMaximale = 4;

    /** Santé minimale (en %) exigée pour déployer un héros. */
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $seuilSanteMinimal = 30;

    /** Cible principale (ex. « Titan d'Or »). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cible = null;

    /** État courant de la cible (ex. « Surchauffe 784°C (+12°/min) »). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $etatCible = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateFinReelle = null;

    /** @var Collection<int, ObjectifMission>|null */
    #[ORM\OneToMany(targetEntity: ObjectifMission::class, mappedBy: 'mission', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordreAffichage' => 'ASC'])]
    private ?Collection $objectifs = null;

    /** @var Collection<int, AffectationMission>|null */
    #[ORM\OneToMany(targetEntity: AffectationMission::class, mappedBy: 'mission', cascade: ['persist'])]
    private ?Collection $affectations = null;

    /** @var Collection<int, Villain>|null */
    #[ORM\ManyToMany(targetEntity: Villain::class, inversedBy: 'missions')]
    #[ORM\JoinTable(name: 'mission_villain')]
    private ?Collection $villains = null;

    /** @var Collection<int, Aptitude>|null */
    #[ORM\ManyToMany(targetEntity: Aptitude::class)]
    #[ORM\JoinTable(name: 'mission_aptitude')]
    private ?Collection $aptitudesRequises = null;

    public function getDateFinPrevue(): ?\DateTimeImmutable { return $this->timer; }
    public function setDateFinPrevue(\DateTimeImmutable $date): static { return $this->setTimer($date); }

    public function getDifficulte(): ?Difficulte
    {
        return null === $this->niveauDeDifficulte ? null : Difficulte::tryFrom($this->niveauDeDifficulte);
    }

    public function setDifficulte(Difficulte $difficulte): static
    {
        return $this->setNiveauDeDifficulte($difficulte->value);
    }

    public function getLocalisation(): ?string { return $this->localisation; }
    public function setLocalisation(?string $l): static { $this->localisation = $l; return $this; }
    public function getPositionX(): float { return $this->positionX; }
    public function setPositionX(float $x): static { $this->positionX = $x; return $this; }
    public function getPositionY(): float { return $this->positionY; }
    public function setPositionY(float $y): static { $this->positionY = $y; return $this; }
    public function getStatut(): MissionStatut { return $this->statut; }
    public function setStatut(MissionStatut $s): static { $this->statut = $s; return $this; }
    public function getNiveauUrgence(): NiveauUrgence { return $this->niveauUrgence; }
    public function setNiveauUrgence(NiveauUrgence $n): static { $this->niveauUrgence = $n; return $this; }
    public function getTailleEquipeMinimale(): int { return $this->tailleEquipeMinimale; }
    public function setTailleEquipeMinimale(int $t): static { $this->tailleEquipeMinimale = $t; return $this; }
    public function getTailleEquipeMaximale(): int { return $this->tailleEquipeMaximale; }
    public function setTailleEquipeMaximale(int $t): static { $this->tailleEquipeMaximale = $t; return $this; }
    public function getSeuilSanteMinimal(): int { return $this->seuilSanteMinimal; }
    public function setSeuilSanteMinimal(int $s): static { $this->seuilSanteMinimal = $s; return $this; }
    public function getCible(): ?string { return $this->cible; }
    public function setCible(?string $c): static { $this->cible = $c; return $this; }
    public function getEtatCible(): ?string { return $this->etatCible; }
    public function setEtatCible(?string $e): static { $this->etatCible = $e; return $this; }
    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeImmutable $d): static { $this->dateDebut = $d; return $this; }
    public function getDateFinReelle(): ?\DateTimeImmutable { return $this->dateFinReelle; }
    public function setDateFinReelle(?\DateTimeImmutable $d): static { $this->dateFinReelle = $d; return $this; }

    /** Statistique minimale requise (colonnes existantes *Requis). */
    public function getStatRequise(Statistique $statistique): ?int
    {
        return match ($statistique) {
            Statistique::COMBAT => $this->combatRequis,
            Statistique::INTELLIGENCE => $this->intelligenceRequis,
            Statistique::CHARISME => $this->charismeRequis,
            Statistique::MOBILITE => $this->mobiliteRequis,
            Statistique::VIGUEUR => $this->vigueurRequis,
            Statistique::GLOBAL => null,
        };
    }

    /** @return array<string, int> exigences non nulles et > 0, clé = valeur de Statistique */
    public function getStatsRequises(): array
    {
        $requises = [];
        foreach (Statistique::heroStats() as $stat) {
            $valeur = $this->getStatRequise($stat);
            if (null !== $valeur && $valeur > 0) {
                $requises[$stat->value] = $valeur;
            }
        }

        return $requises;
    }

    /** @return Collection<int, ObjectifMission> */
    public function getObjectifs(): Collection { return $this->objectifs ??= new ArrayCollection(); }

    public function addObjectif(ObjectifMission $objectif): static
    {
        if (!$this->getObjectifs()->contains($objectif)) {
            $this->getObjectifs()->add($objectif);
            $objectif->setMission($this);
        }

        return $this;
    }

    public function removeObjectif(ObjectifMission $objectif): static
    {
        $this->getObjectifs()->removeElement($objectif);

        return $this;
    }

    /** @return Collection<int, AffectationMission> */
    public function getAffectations(): Collection { return $this->affectations ??= new ArrayCollection(); }

    public function addAffectation(AffectationMission $affectation): static
    {
        if (!$this->getAffectations()->contains($affectation)) {
            $this->getAffectations()->add($affectation);
            $affectation->setMission($this);
        }

        return $this;
    }

    /** @return list<AffectationMission> */
    public function getAffectationsActives(): array
    {
        return array_values($this->getAffectations()->filter(
            static fn (AffectationMission $a) => $a->getStatut()->isActive()
        )->toArray());
    }

    /** @return list<Hero> */
    public function getHerosAffectes(): array
    {
        return array_map(static fn (AffectationMission $a) => $a->getHero(), $this->getAffectationsActives());
    }

    /** @return Collection<int, Villain> */
    public function getVillains(): Collection { return $this->villains ??= new ArrayCollection(); }

    public function addVillain(Villain $villain): static
    {
        if (!$this->getVillains()->contains($villain)) {
            $this->getVillains()->add($villain);
        }

        return $this;
    }

    public function removeVillain(Villain $villain): static
    {
        $this->getVillains()->removeElement($villain);

        return $this;
    }

    /** Niveau de menace maximal des vilains associés (0 si aucun). */
    public function getNiveauMenaceMax(): int
    {
        $max = 0;
        foreach ($this->getVillains() as $villain) {
            $max = max($max, $villain->getNiveauMenace());
        }

        return $max;
    }

    /** @return Collection<int, Aptitude> */
    public function getAptitudesRequises(): Collection { return $this->aptitudesRequises ??= new ArrayCollection(); }

    public function addAptitudeRequise(Aptitude $aptitude): static
    {
        if (!$this->getAptitudesRequises()->contains($aptitude)) {
            $this->getAptitudesRequises()->add($aptitude);
        }

        return $this;
    }

    public function removeAptitudeRequise(Aptitude $aptitude): static
    {
        $this->getAptitudesRequises()->removeElement($aptitude);

        return $this;
    }

    /** Progression globale (moyenne des objectifs). */
    public function getProgression(): int
    {
        $objectifs = $this->getObjectifs();
        if (0 === $objectifs->count()) {
            return 0;
        }
        $total = 0;
        foreach ($objectifs as $objectif) {
            $total += $objectif->getProgression();
        }

        return (int) round($total / $objectifs->count());
    }

    public function isCritique(): bool
    {
        return NiveauUrgence::CRITIQUE === $this->niveauUrgence || Difficulte::S === $this->getDifficulte();
    }

    #[Assert\Callback]
    public function validerMission(ExecutionContextInterface $context): void
    {
        if (null !== $this->tauxDeReussite && ($this->tauxDeReussite < 0 || $this->tauxDeReussite > 100)) {
            $context->buildViolation('Le taux de réussite doit être compris entre 0 et 100.')->atPath('tauxDeReussite')->addViolation();
        }
        if (null !== $this->niveauDeDifficulte && null === Difficulte::tryFrom($this->niveauDeDifficulte)) {
            $context->buildViolation('Difficulté inconnue.')->atPath('difficulte')->addViolation();
        }
        if ($this->tailleEquipeMaximale < $this->tailleEquipeMinimale) {
            $context->buildViolation('La taille maximale doit être supérieure ou égale à la taille minimale.')
                ->atPath('tailleEquipeMaximale')->addViolation();
        }
        foreach (Statistique::heroStats() as $stat) {
            $valeur = $this->getStatRequise($stat);
            if (null !== $valeur && ($valeur < 0 || $valeur > 100)) {
                $context->buildViolation('Une exigence doit être comprise entre 0 et 100.')
                    ->atPath(strtolower($stat->value).'Requis')->addViolation();
            }
        }
        if (null !== $this->dateDebut && null !== $this->timer && $this->timer <= $this->dateDebut) {
            $context->buildViolation('La fin prévue doit être postérieure au début.')->atPath('dateFinPrevue')->addViolation();
        }
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }

    // ------------------------------------------------------------------
    // Ajouts (additifs) — carte réelle : position GPS de l'intervention.
    // positionX / positionY (grille abstraite) restent inchangés.
    // ------------------------------------------------------------------

    #[ORM\OneToOne(cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'geo_position_id', nullable: true, onDelete: 'SET NULL')]
    private ?GeoPosition $geoPosition = null;

    public function getGeoPosition(): ?GeoPosition { return $this->geoPosition; }
    public function setGeoPosition(?GeoPosition $geoPosition): static { $this->geoPosition = $geoPosition; return $this; }

    public function getCoordonnees(): ?\App\Geo\Coordonnees
    {
        return $this->geoPosition?->getCoordonnees();
    }

    public function isGeolocalisee(): bool
    {
        return null !== $this->getCoordonnees();
    }

    /** @return list<ObjectifMission> objectifs obligatoires (ordre d'affichage) */
    public function getObjectifsObligatoires(): array
    {
        return array_values($this->getObjectifs()->filter(static fn (ObjectifMission $o) => $o->isObligatoire())->toArray());
    }

    /** Tous les objectifs obligatoires sont-ils terminés ? (faux s'il n'y en a aucun) */
    public function isObjectifsObligatoiresAtteints(): bool
    {
        $obligatoires = $this->getObjectifsObligatoires();
        if ([] === $obligatoires) {
            return false;
        }
        foreach ($obligatoires as $objectif) {
            if (!$objectif->isTermine()) {
                return false;
            }
        }

        return true;
    }
}
