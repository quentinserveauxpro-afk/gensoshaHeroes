<?php

namespace App\Entity;

use App\Enum\MouvementStatut;
use App\Geo\Coordonnees;
use App\Repository\HeroMovementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Déplacement d'un héros vers une mission.
 *
 * Le trajet est une SIMULATION EN LIGNE DROITE (arc de grand cercle) et non un itinéraire
 * routier : aucun moteur d'itinéraire n'est installé. Le serveur est la seule source de vérité :
 * la position courante et l'arrivée sont calculées par HeroMovementService.
 */
#[ORM\Entity(repositoryClass: HeroMovementRepository::class)]
#[ORM\Index(columns: ['statut'], name: 'idx_hero_movement_statut')]
class HeroMovement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Hero $hero;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Mission $mission;

    /** Affectation à l'origine du déplacement (null si l'affectation a été supprimée). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AffectationMission $affectation = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7)]
    private string $latitudeDepart;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7)]
    private string $longitudeDepart;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7)]
    private string $latitudeArrivee;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7)]
    private string $longitudeArrivee;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7)]
    private string $latitudeActuelle;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7)]
    private string $longitudeActuelle;

    /** Distance totale (km, Haversine). */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private float $distanceKm = 0.0;

    #[ORM\Column]
    private \DateTimeImmutable $dateDepart;

    #[ORM\Column]
    private \DateTimeImmutable $dateArriveePrevue;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateArriveeReelle = null;

    /** Vitesse en km/h (temps réel, hors facteur d'accélération de la simulation). */
    #[ORM\Column]
    #[Assert\Positive]
    private float $vitesse;

    /** Progression du trajet, de 0 à 100 %. */
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private float $progression = 0.0;

    #[ORM\Column(enumType: MouvementStatut::class, length: 20)]
    private MouvementStatut $statut = MouvementStatut::EN_ATTENTE;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Hero $hero,
        Mission $mission,
        Coordonnees $depart,
        Coordonnees $arrivee,
        float $distanceKm,
        float $vitesse,
        \DateTimeImmutable $dateDepart,
        \DateTimeImmutable $dateArriveePrevue,
        ?AffectationMission $affectation = null,
    ) {
        if ($vitesse <= 0) {
            throw new \InvalidArgumentException('La vitesse doit être strictement positive.');
        }
        $this->hero = $hero;
        $this->mission = $mission;
        $this->affectation = $affectation;
        $this->latitudeDepart = $depart->latitudeDecimale();
        $this->longitudeDepart = $depart->longitudeDecimale();
        $this->latitudeArrivee = $arrivee->latitudeDecimale();
        $this->longitudeArrivee = $arrivee->longitudeDecimale();
        $this->latitudeActuelle = $depart->latitudeDecimale();
        $this->longitudeActuelle = $depart->longitudeDecimale();
        $this->distanceKm = max(0.0, $distanceKm);
        $this->vitesse = $vitesse;
        $this->dateDepart = $dateDepart;
        $this->dateArriveePrevue = $dateArriveePrevue;
        $this->updatedAt = $dateDepart;
    }

    public function getId(): ?int { return $this->id; }
    public function getHero(): Hero { return $this->hero; }
    public function getMission(): Mission { return $this->mission; }
    public function getAffectation(): ?AffectationMission { return $this->affectation; }
    public function getLatitudeDepart(): string { return $this->latitudeDepart; }
    public function getLongitudeDepart(): string { return $this->longitudeDepart; }
    public function getLatitudeArrivee(): string { return $this->latitudeArrivee; }
    public function getLongitudeArrivee(): string { return $this->longitudeArrivee; }
    public function getLatitudeActuelle(): string { return $this->latitudeActuelle; }
    public function getLongitudeActuelle(): string { return $this->longitudeActuelle; }
    public function getDistanceKm(): float { return $this->distanceKm; }
    public function getDateDepart(): \DateTimeImmutable { return $this->dateDepart; }
    public function getDateArriveePrevue(): \DateTimeImmutable { return $this->dateArriveePrevue; }
    public function getDateArriveeReelle(): ?\DateTimeImmutable { return $this->dateArriveeReelle; }
    public function getVitesse(): float { return $this->vitesse; }
    public function getProgression(): float { return $this->progression; }
    public function getStatut(): MouvementStatut { return $this->statut; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getDepart(): Coordonnees
    {
        return new Coordonnees((float) $this->latitudeDepart, (float) $this->longitudeDepart);
    }

    public function getArrivee(): Coordonnees
    {
        return new Coordonnees((float) $this->latitudeArrivee, (float) $this->longitudeArrivee);
    }

    public function getPositionActuelle(): Coordonnees
    {
        return new Coordonnees((float) $this->latitudeActuelle, (float) $this->longitudeActuelle);
    }

    /** Enregistre un point intermédiaire calculé par le serveur. */
    public function avancer(Coordonnees $position, float $progression, \DateTimeImmutable $now): static
    {
        $this->latitudeActuelle = $position->latitudeDecimale();
        $this->longitudeActuelle = $position->longitudeDecimale();
        $this->progression = round(max(0.0, min(100.0, $progression)), 2);
        $this->statut = MouvementStatut::EN_DEPLACEMENT;
        $this->updatedAt = $now;

        return $this;
    }

    /** Arrivée : position = destination, progression = 100 %, heure réelle enregistrée. */
    public function arriver(\DateTimeImmutable $dateArriveeReelle): static
    {
        $this->latitudeActuelle = $this->latitudeArrivee;
        $this->longitudeActuelle = $this->longitudeArrivee;
        $this->progression = 100.0;
        $this->statut = MouvementStatut::ARRIVE;
        $this->dateArriveeReelle = $dateArriveeReelle;
        $this->updatedAt = $dateArriveeReelle;

        return $this;
    }

    public function interrompre(\DateTimeImmutable $now): static
    {
        if ($this->statut->isEnCours()) {
            $this->statut = MouvementStatut::INTERROMPU;
            $this->updatedAt = $now;
        }

        return $this;
    }

    public function isEnCours(): bool
    {
        return $this->statut->isEnCours();
    }
}
