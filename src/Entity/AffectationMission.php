<?php

namespace App\Entity;

use App\Enum\AffectationStatut;
use App\Enum\RoleEquipe;
use App\Repository\AffectationMissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lien historisé entre un héros et une mission (remplace une ManyToMany directe).
 */
#[ORM\Entity(repositoryClass: AffectationMissionRepository::class)]
#[ORM\Index(columns: ['statut'], name: 'idx_affectation_statut')]
class AffectationMission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'affectations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Hero $hero = null;

    #[ORM\ManyToOne(inversedBy: 'affectations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Mission $mission = null;

    #[ORM\Column(enumType: RoleEquipe::class, length: 20)]
    private RoleEquipe $role = RoleEquipe::MEMBRE;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateAffectation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateArrivee = null;

    #[ORM\Column(enumType: AffectationStatut::class, length: 20)]
    private AffectationStatut $statut = AffectationStatut::AFFECTE;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $pointsDeVieAuDepart = 0;

    /** Contribution du héros au résultat, en pourcentage (renseignée à la clôture). */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $contribution = null;

    public function __construct(?Hero $hero = null, ?Mission $mission = null, ?\DateTimeImmutable $dateAffectation = null)
    {
        $this->hero = $hero;
        $this->mission = $mission;
        $this->dateAffectation = $dateAffectation ?? new \DateTimeImmutable();
        if (null !== $hero) {
            $this->pointsDeVieAuDepart = (int) $hero->getPointsDeVie();
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getHero(): ?Hero { return $this->hero; }
    public function setHero(Hero $hero): static { $this->hero = $hero; return $this; }
    public function getMission(): ?Mission { return $this->mission; }
    public function setMission(Mission $mission): static { $this->mission = $mission; return $this; }
    public function getRole(): RoleEquipe { return $this->role; }
    public function setRole(RoleEquipe $role): static { $this->role = $role; return $this; }
    public function getDateAffectation(): ?\DateTimeImmutable { return $this->dateAffectation; }
    public function setDateAffectation(\DateTimeImmutable $d): static { $this->dateAffectation = $d; return $this; }
    public function getDateArrivee(): ?\DateTimeImmutable { return $this->dateArrivee; }
    public function setDateArrivee(?\DateTimeImmutable $d): static { $this->dateArrivee = $d; return $this; }
    public function getStatut(): AffectationStatut { return $this->statut; }
    public function setStatut(AffectationStatut $statut): static { $this->statut = $statut; return $this; }
    public function getPointsDeVieAuDepart(): int { return $this->pointsDeVieAuDepart; }
    public function setPointsDeVieAuDepart(int $pv): static { $this->pointsDeVieAuDepart = max(0, $pv); return $this; }
    public function getContribution(): ?int { return $this->contribution; }
    public function setContribution(?int $c): static { $this->contribution = null === $c ? null : max(0, min(100, $c)); return $this; }

    public function isActive(): bool
    {
        return $this->statut->isActive();
    }
}
