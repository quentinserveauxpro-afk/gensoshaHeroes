<?php

namespace App\Entity;

use App\Enum\SourceGeolocalisation;
use App\Geo\Coordonnees;
use App\Repository\GeoPositionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Position géographique réelle (WGS 84) d'un héros, d'une mission ou d'une requête citoyenne.
 *
 * Entité additive : les attributs historiques positionX / positionY (grille abstraite)
 * ne sont ni modifiés ni remplacés. L'adresse saisie est conservée à côté des coordonnées
 * obtenues afin de pouvoir revérifier ou regéocoder.
 */
#[ORM\Entity(repositoryClass: GeoPositionRepository::class)]
#[ORM\Index(columns: ['latitude', 'longitude'], name: 'idx_geo_position_coords')]
class GeoPosition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** DECIMAL(10, 7) : chaîne côté PHP (DBAL 4), précision ≈ 1 cm. */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7)]
    #[Assert\NotBlank]
    #[Assert\Range(min: -90, max: 90)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7)]
    #[Assert\NotBlank]
    #[Assert\Range(min: -180, max: 180)]
    private ?string $longitude = null;

    /** Adresse telle que saisie (jamais réécrite par le géocodage). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresseSaisie = null;

    /** Libellé normalisé renvoyé par le fournisseur de géocodage. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(enumType: SourceGeolocalisation::class, length: 20)]
    private SourceGeolocalisation $source = SourceGeolocalisation::REFERENCE;

    /** Fournisseur utilisé (ex. « nominatim »), pour la traçabilité. */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $fournisseur = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(?Coordonnees $coordonnees = null, SourceGeolocalisation $source = SourceGeolocalisation::REFERENCE)
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->source = $source;
        if (null !== $coordonnees) {
            $this->setCoordonnees($coordonnees);
        }
    }

    public static function en(float $latitude, float $longitude, SourceGeolocalisation $source = SourceGeolocalisation::REFERENCE): self
    {
        return new self(new Coordonnees($latitude, $longitude), $source);
    }

    public function getId(): ?int { return $this->id; }
    public function getLatitude(): ?string { return $this->latitude; }
    public function getLongitude(): ?string { return $this->longitude; }

    public function getCoordonnees(): ?Coordonnees
    {
        return Coordonnees::depuisDecimaux($this->latitude, $this->longitude);
    }

    public function setCoordonnees(Coordonnees $coordonnees, ?SourceGeolocalisation $source = null): static
    {
        $this->latitude = $coordonnees->latitudeDecimale();
        $this->longitude = $coordonnees->longitudeDecimale();
        if (null !== $source) {
            $this->source = $source;
        }
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAdresseSaisie(): ?string { return $this->adresseSaisie; }
    public function setAdresseSaisie(?string $a): static { $this->adresseSaisie = $a; return $this; }
    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(?string $l): static { $this->libelle = $l; return $this; }
    public function getCodePostal(): ?string { return $this->codePostal; }
    public function setCodePostal(?string $c): static { $this->codePostal = $c; return $this; }
    public function getVille(): ?string { return $this->ville; }
    public function setVille(?string $v): static { $this->ville = $v; return $this; }
    public function getSource(): SourceGeolocalisation { return $this->source; }
    public function setSource(SourceGeolocalisation $s): static { $this->source = $s; return $this; }
    public function getFournisseur(): ?string { return $this->fournisseur; }
    public function setFournisseur(?string $f): static { $this->fournisseur = $f; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
