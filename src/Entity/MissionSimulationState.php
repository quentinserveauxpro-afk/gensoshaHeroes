<?php

namespace App\Entity;

use App\Repository\MissionSimulationStateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * État persistant du moteur de simulation d'une mission (entité additive, 1-1 avec Mission).
 *
 * dernierTick + nombreEtapes rendent la commande app:missions:update idempotente :
 * seules les étapes entières écoulées depuis le dernier traitement sont calculées.
 */
#[ORM\Entity(repositoryClass: MissionSimulationStateRepository::class)]
class MissionSimulationState
{
    public const JOURNAL_MAX = 20;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private Mission $mission;

    /** Instant (temps réel) jusqu'auquel la simulation a été calculée. */
    #[ORM\Column]
    private \DateTimeImmutable $dernierTick;

    #[ORM\Column]
    private int $nombreEtapes = 0;

    /** Arrivée du premier héros sur place : début de l'engagement. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateEngagement = null;

    /** Niveau de menace (0-100) au début de l'engagement. */
    #[ORM\Column(nullable: true)]
    private ?float $menaceInitiale = null;

    /** Niveau de menace courant (0-100). */
    #[ORM\Column]
    private float $menaceActuelle = 0.0;

    /** Taux de réussite estimé en direct (0-100). */
    #[ORM\Column]
    private int $tauxReussiteDirect = 0;

    /** Héros pour lesquels l'alerte « santé critique » a déjà été émise. @var list<int> */
    #[ORM\Column]
    private array $alertesSante = [];

    /** Derniers événements (texte, niveau, horodatage) pour l'affichage. @var list<array{t: string, niveau: string, message: string}> */
    #[ORM\Column]
    private array $journal = [];

    /**
     * Progression précise (décimale) des objectifs, indexée par id d'objectif.
     * ObjectifMission::$progression étant entière, les gains fractionnaires sont cumulés ici.
     *
     * @var array<int|string, float>
     */
    #[ORM\Column]
    private array $progressions = [];

    /** Contribution cumulée de chaque héros (points d'objectif), indexée par id de héros. @var array<int|string, float> */
    #[ORM\Column]
    private array $contributions = [];

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $issue = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Mission $mission, \DateTimeImmutable $debut)
    {
        $this->mission = $mission;
        $this->dernierTick = $debut;
        $this->updatedAt = $debut;
    }

    public function getId(): ?int { return $this->id; }
    public function getMission(): Mission { return $this->mission; }
    public function getDernierTick(): \DateTimeImmutable { return $this->dernierTick; }
    public function getNombreEtapes(): int { return $this->nombreEtapes; }
    public function getDateEngagement(): ?\DateTimeImmutable { return $this->dateEngagement; }
    public function getMenaceInitiale(): ?float { return $this->menaceInitiale; }
    public function getMenaceActuelle(): float { return $this->menaceActuelle; }
    public function getTauxReussiteDirect(): int { return $this->tauxReussiteDirect; }
    /** @return list<int> */
    public function getAlertesSante(): array { return $this->alertesSante; }
    /** @return list<array{t: string, niveau: string, message: string}> */
    public function getJournal(): array { return $this->journal; }
    public function getIssue(): ?string { return $this->issue; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function avancer(int $etapes, \DateTimeImmutable $jusqua): static
    {
        $this->nombreEtapes += max(0, $etapes);
        $this->dernierTick = $jusqua;
        $this->updatedAt = $jusqua;

        return $this;
    }

    public function engager(\DateTimeImmutable $date, float $menace): static
    {
        if (null === $this->dateEngagement) {
            $this->dateEngagement = $date;
            $this->menaceInitiale = $menace;
            $this->menaceActuelle = $menace;
        }

        return $this;
    }

    public function setMenaceActuelle(float $menace): static
    {
        $this->menaceActuelle = round(max(0.0, min(100.0, $menace)), 2);

        return $this;
    }

    public function setTauxReussiteDirect(int $taux): static
    {
        $this->tauxReussiteDirect = max(0, min(100, $taux));

        return $this;
    }

    public function aEmisAlerteSante(int $heroId): bool
    {
        return \in_array($heroId, $this->alertesSante, true);
    }

    public function marquerAlerteSante(int $heroId): static
    {
        if (!$this->aEmisAlerteSante($heroId)) {
            $this->alertesSante[] = $heroId;
        }

        return $this;
    }

    public function journaliser(\DateTimeImmutable $t, string $niveau, string $message): static
    {
        $this->journal[] = ['t' => $t->format(\DATE_ATOM), 'niveau' => $niveau, 'message' => $message];
        $this->journal = \array_slice($this->journal, -self::JOURNAL_MAX);

        return $this;
    }

    public function getProgressionPrecise(int $objectifId, int $defaut): float
    {
        return (float) ($this->progressions[(string) $objectifId] ?? $defaut);
    }

    public function setProgressionPrecise(int $objectifId, float $valeur): static
    {
        $this->progressions[(string) $objectifId] = round(max(0.0, min(100.0, $valeur)), 4);

        return $this;
    }

    public function ajouterContribution(int $heroId, float $points): static
    {
        $this->contributions[(string) $heroId] = round(($this->contributions[(string) $heroId] ?? 0.0) + max(0.0, $points), 4);

        return $this;
    }

    /** @return array<int|string, float> */
    public function getContributions(): array { return $this->contributions; }

    public function terminer(string $issue, \DateTimeImmutable $date): static
    {
        $this->issue = $issue;
        $this->updatedAt = $date;

        return $this;
    }

    // ------------------------------------------------------------------
    // Ajouts (additifs) — infirmerie : fatigue accumulée pendant l'engagement.
    // ------------------------------------------------------------------

    /** Fatigue décimale par héros (clé = id du héros). @var array<string, float> */
    #[ORM\Column(type: 'json')]
    private array $fatigues = [];

    public function getFatiguePrecise(int $heroId, float $defaut): float
    {
        return (float) ($this->fatigues[(string) $heroId] ?? $defaut);
    }

    public function setFatiguePrecise(int $heroId, float $valeur): static
    {
        $this->fatigues[(string) $heroId] = round(max(0.0, min(100.0, $valeur)), 4);

        return $this;
    }
}
