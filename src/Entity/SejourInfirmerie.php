<?php

namespace App\Entity;

use App\Enum\MotifInfirmerie;
use App\Enum\StatutSejour;
use App\Enum\TypeAdmission;
use App\Geo\Coordonnees;
use App\Repository\SejourInfirmerieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Séjour d'un héros à l'infirmerie (historique conservé : un séjour terminé n'est jamais supprimé).
 *
 * Unicité du séjour actif garantie en base : `cle_active` vaut l'identifiant du héros tant que le
 * séjour est actif, NULL ensuite ; un index unique sur cette colonne interdit deux séjours actifs.
 *
 * Le trajet de retour au QG est porté par le séjour (HeroMovement exige une mission) ; la position
 * pendant le retour est une fonction pure du temps, comme pour les déplacements vers les missions.
 * Santé et fatigue sont suivies avec précision décimale ; les valeurs entières du héros en dérivent.
 */
#[ORM\Entity(repositoryClass: SejourInfirmerieRepository::class)]
#[ORM\Index(name: 'idx_sejour_infirmerie_statut', columns: ['statut'])]
class SejourInfirmerie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Hero $hero;

    #[ORM\Column(enumType: MotifInfirmerie::class, length: 40)]
    private MotifInfirmerie $motif;

    #[ORM\Column(enumType: TypeAdmission::class, length: 20)]
    private TypeAdmission $typeAdmission;

    #[ORM\Column(enumType: StatutSejour::class, length: 30)]
    private StatutSejour $statut = StatutSejour::RETOUR_EN_ATTENTE;

    #[ORM\Column(unique: true, nullable: true)]
    private ?int $cleActive = null;

    #[ORM\Column]
    private \DateTimeImmutable $admisLe;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $debutTraitementLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sortiePrevueLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $retabliLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sortiLe = null;

    #[ORM\Column]
    private int $santeAdmission;

    #[ORM\Column]
    private int $fatigueAdmission;

    /** Points de vie courants (décimaux), bornés entre 0 et le maximum du héros. */
    #[ORM\Column]
    private float $santeActuelle;

    /** Fatigue courante (décimale), bornée entre 0 et 100. */
    #[ORM\Column]
    private float $fatigueActuelle;

    /** Points de vie à atteindre pour sortir. */
    #[ORM\Column]
    private int $santeCible;

    /** Fatigue maximale pour sortir. */
    #[ORM\Column]
    private int $fatigueCible;

    /** Points de vie récupérés par minute simulée. */
    #[ORM\Column]
    private float $tauxSante = 0.0;

    /** Points de fatigue éliminés par minute simulée. */
    #[ORM\Column]
    private float $tauxFatigue = 0.0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $demandePar = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Mission $mission = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    // --- Trajet de retour au QG ---
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitudeDepart = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitudeDepart = null;

    #[ORM\Column(nullable: true)]
    private ?float $distanceRetourKm = null;

    #[ORM\Column(nullable: true)]
    private ?float $vitesseRetour = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $departRetourLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $arriveeQgPrevueLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $arriveeQgLe = null;

    /** Instant jusqu'auquel la récupération a été calculée et enregistrée. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dernierCalculLe = null;

    /** Codes des alertes déjà émises pour ce séjour (anti-doublon). @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $alertesEmises = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Hero $hero, MotifInfirmerie $motif, TypeAdmission $type, int $santeCible, int $fatigueCible, \DateTimeImmutable $admisLe)
    {
        $this->hero = $hero;
        $this->motif = $motif;
        $this->typeAdmission = $type;
        $this->admisLe = $admisLe;
        $this->createdAt = $admisLe;
        $this->updatedAt = $admisLe;
        $this->santeAdmission = max(0, (int) $hero->getPointsDeVie());
        $this->fatigueAdmission = $hero->getFatigue();
        $this->santeActuelle = (float) min(max(0, (int) $hero->getPointsDeVie()), max(0, $hero->getPointsDeVieMaximum()));
        $this->fatigueActuelle = (float) max(0, min(100, $hero->getFatigue()));
        $this->santeCible = max(0, $santeCible);
        $this->fatigueCible = max(0, min(100, $fatigueCible));
        $this->cleActive = $hero->getId();
    }

    // --- Transitions -----------------------------------------------------

    public function commencerRetour(Coordonnees $depart, float $distanceKm, float $vitesse, \DateTimeImmutable $departLe, \DateTimeImmutable $arriveePrevue): static
    {
        $this->latitudeDepart = $depart->latitudeDecimale();
        $this->longitudeDepart = $depart->longitudeDecimale();
        $this->distanceRetourKm = $distanceKm;
        $this->vitesseRetour = $vitesse;
        $this->departRetourLe = $departLe;
        $this->arriveeQgPrevueLe = $arriveePrevue;
        $this->statut = StatutSejour::RETOUR_QG;
        $this->updatedAt = $departLe;

        return $this;
    }

    /** Replanifie le retour en cours (échelle temporelle accélérée) sans changer son point de départ. */
    public function replanifierRetour(\DateTimeImmutable $departLe, \DateTimeImmutable $arriveePrevue, float $vitesse, \DateTimeImmutable $now): static
    {
        $this->departRetourLe = $departLe;
        $this->arriveeQgPrevueLe = max($departLe, $arriveePrevue);
        $this->vitesseRetour = max(0.0, $vitesse);
        $this->updatedAt = $now;

        return $this;
    }

    public function arriverAuQg(\DateTimeImmutable $date): static
    {
        $this->arriveeQgLe = $date;
        $this->debutTraitementLe = $date;
        $this->dernierCalculLe = $date;
        $this->statut = StatutSejour::EN_TRAITEMENT;
        $this->updatedAt = $date;

        return $this;
    }

    public function enregistrerRecuperation(float $sante, float $fatigue, \DateTimeImmutable $jusquA, ?\DateTimeImmutable $sortiePrevue): static
    {
        $this->santeActuelle = $sante;
        $this->fatigueActuelle = $fatigue;
        $this->dernierCalculLe = $jusquA;
        $this->sortiePrevueLe = $sortiePrevue;
        $this->updatedAt = $jusquA;

        return $this;
    }

    public function marquerRetabli(\DateTimeImmutable $date): static
    {
        $this->statut = StatutSejour::RETABLI;
        $this->retabliLe = $date;
        $this->sortiePrevueLe = $date;
        $this->updatedAt = $date;

        return $this;
    }

    public function sortir(\DateTimeImmutable $date): static
    {
        $this->statut = StatutSejour::SORTI;
        $this->sortiLe = $date;
        $this->cleActive = null;
        $this->updatedAt = $date;

        return $this;
    }

    public function annuler(\DateTimeImmutable $date, ?string $raison = null): static
    {
        $this->statut = StatutSejour::ANNULE;
        $this->sortiLe = $date;
        $this->cleActive = null;
        $this->updatedAt = $date;
        if (null !== $raison) {
            $this->notes = trim(($this->notes ?? '')."\n".$raison);
        }

        return $this;
    }

    public function aEmisAlerte(string $code): bool
    {
        return \in_array($code, $this->alertesEmises, true);
    }

    public function marquerAlerte(string $code): static
    {
        if (!$this->aEmisAlerte($code)) {
            $this->alertesEmises[] = $code;
        }

        return $this;
    }

    // --- Accesseurs ------------------------------------------------------

    public function getId(): ?int { return $this->id; }
    public function getHero(): Hero { return $this->hero; }
    public function getMotif(): MotifInfirmerie { return $this->motif; }
    public function getTypeAdmission(): TypeAdmission { return $this->typeAdmission; }
    public function getStatut(): StatutSejour { return $this->statut; }
    public function isActif(): bool { return $this->statut->isActif(); }
    public function getCleActive(): ?int { return $this->cleActive; }
    public function getAdmisLe(): \DateTimeImmutable { return $this->admisLe; }
    public function getDebutTraitementLe(): ?\DateTimeImmutable { return $this->debutTraitementLe; }
    public function getSortiePrevueLe(): ?\DateTimeImmutable { return $this->sortiePrevueLe; }
    public function getRetabliLe(): ?\DateTimeImmutable { return $this->retabliLe; }
    public function getSortiLe(): ?\DateTimeImmutable { return $this->sortiLe; }
    public function getSanteAdmission(): int { return $this->santeAdmission; }
    public function getFatigueAdmission(): int { return $this->fatigueAdmission; }
    public function getSanteActuelle(): float { return $this->santeActuelle; }
    public function getFatigueActuelle(): float { return $this->fatigueActuelle; }
    public function getSanteCible(): int { return $this->santeCible; }
    public function getFatigueCible(): int { return $this->fatigueCible; }
    public function getTauxSante(): float { return $this->tauxSante; }
    public function getTauxFatigue(): float { return $this->tauxFatigue; }
    public function setTaux(float $sante, float $fatigue): static { $this->tauxSante = max(0.0, $sante); $this->tauxFatigue = max(0.0, $fatigue); return $this; }
    public function getDemandePar(): ?User { return $this->demandePar; }
    public function setDemandePar(?User $u): static { $this->demandePar = $u; return $this; }
    public function getMission(): ?Mission { return $this->mission; }
    public function setMission(?Mission $m): static { $this->mission = $m; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): static { $this->notes = $n; return $this; }
    public function getDistanceRetourKm(): ?float { return $this->distanceRetourKm; }
    public function getVitesseRetour(): ?float { return $this->vitesseRetour; }
    public function getDepartRetourLe(): ?\DateTimeImmutable { return $this->departRetourLe; }
    public function getArriveeQgPrevueLe(): ?\DateTimeImmutable { return $this->arriveeQgPrevueLe; }
    public function getArriveeQgLe(): ?\DateTimeImmutable { return $this->arriveeQgLe; }
    public function getDernierCalculLe(): ?\DateTimeImmutable { return $this->dernierCalculLe; }
    /** @return list<string> */
    public function getAlertesEmises(): array { return $this->alertesEmises; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getDepartRetour(): ?Coordonnees
    {
        return null === $this->latitudeDepart || null === $this->longitudeDepart
            ? null : Coordonnees::depuisDecimaux($this->latitudeDepart, $this->longitudeDepart);
    }

    /** Durée de traitement (début → rétablissement ou sortie), en secondes. */
    public function getDureeTraitement(): ?int
    {
        $fin = $this->retabliLe ?? $this->sortiLe;
        if (null === $this->debutTraitementLe || null === $fin) {
            return null;
        }

        return max(0, $fin->getTimestamp() - $this->debutTraitementLe->getTimestamp());
    }
}
