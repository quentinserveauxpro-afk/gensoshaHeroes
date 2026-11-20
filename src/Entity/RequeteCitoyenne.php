<?php

namespace App\Entity;

use App\Enum\NiveauUrgence;
use App\Enum\OrigineAlerte;
use App\Enum\RequeteCategorie;
use App\Enum\RequeteStatut;
use App\Repository\RequeteCitoyenneRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RequeteCitoyenneRepository::class)]
class RequeteCitoyenne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $adresse = null;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private float $positionX = 50.0;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private float $positionY = 50.0;

    #[ORM\Column(enumType: NiveauUrgence::class, length: 20)]
    private NiveauUrgence $niveauUrgence = NiveauUrgence::MOYENNE;

    #[ORM\Column(enumType: RequeteStatut::class, length: 20)]
    private RequeteStatut $statut = RequeteStatut::NOUVELLE;

    #[ORM\Column(enumType: RequeteCategorie::class, length: 30)]
    private RequeteCategorie $categorie = RequeteCategorie::ASSISTANCE_PUBLIQUE;

    #[ORM\Column(enumType: OrigineAlerte::class, length: 20)]
    private OrigineAlerte $origine = OrigineAlerte::CIVIL;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nomDemandeur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Mission $mission = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $d): static { $this->description = $d; return $this; }
    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(string $a): static { $this->adresse = $a; return $this; }
    public function getPositionX(): float { return $this->positionX; }
    public function setPositionX(float $x): static { $this->positionX = $x; return $this; }
    public function getPositionY(): float { return $this->positionY; }
    public function setPositionY(float $y): static { $this->positionY = $y; return $this; }
    public function getNiveauUrgence(): NiveauUrgence { return $this->niveauUrgence; }
    public function setNiveauUrgence(NiveauUrgence $n): static { $this->niveauUrgence = $n; return $this; }
    public function getStatut(): RequeteStatut { return $this->statut; }
    public function setStatut(RequeteStatut $s): static { $this->statut = $s; return $this; }
    public function getCategorie(): RequeteCategorie { return $this->categorie; }
    public function setCategorie(RequeteCategorie $c): static { $this->categorie = $c; return $this; }
    public function getOrigine(): OrigineAlerte { return $this->origine; }
    public function setOrigine(OrigineAlerte $o): static { $this->origine = $o; return $this; }
    public function getNomDemandeur(): ?string { return $this->nomDemandeur; }
    public function setNomDemandeur(string $n): static { $this->nomDemandeur = $n; return $this; }
    public function getDateCreation(): ?\DateTimeImmutable { return $this->dateCreation; }
    public function setDateCreation(\DateTimeImmutable $d): static { $this->dateCreation = $d; return $this; }
    public function getMission(): ?Mission { return $this->mission; }
    public function setMission(?Mission $mission): static { $this->mission = $mission; return $this; }

    public function getReference(): string
    {
        return sprintf('REQ-%04d', (int) $this->id);
    }

    // ------------------------------------------------------------------
    // Ajouts (additifs) — formulaire public et traitement opérateur.
    // - $categorie / $statut (enums historiques) restent renseignés : les nouveaux
    //   champs détaillés y sont projetés par RequeteTraitementService.
    // - $niveauUrgence est le niveau INTERNE (décision opérateur) ; le niveau estimé
    //   par le citoyen est conservé séparément dans $urgenceDeclaree.
    // - Le constructeur existant n'étant pas modifiable, la collection d'historique
    //   est initialisée à la demande.
    // ------------------------------------------------------------------

    #[ORM\Column(enumType: \App\Enum\CategorieIncident::class, length: 30, nullable: true)]
    private ?\App\Enum\CategorieIncident $categorieIncident = null;

    #[ORM\Column(enumType: \App\Enum\StatutTraitement::class, length: 30, nullable: true)]
    private ?\App\Enum\StatutTraitement $statutTraitement = null;

    /** Urgence estimée par le demandeur : simple indication, jamais une décision officielle. */
    #[ORM\Column(enumType: NiveauUrgence::class, length: 20, nullable: true)]
    private ?NiveauUrgence $urgenceDeclaree = null;

    /** Vrai tant qu'un opérateur n'a pas fixé l'urgence interne d'une requête publique. */
    #[ORM\Column(options: ['default' => false])]
    private bool $urgenceAQualifier = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $soumissionPublique = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $complementAdresse = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $ville = null;

    /** Secteur opérationnel (département), déduit du code postal ou saisi par l'opérateur. */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $secteur = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $emailDemandeur = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephoneDemandeur = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $consentementLe = null;

    /** Compléments apportés par l'opérateur lors de l'analyse. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $informationsComplementaires = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motifRejet = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $traiteePar = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateDernierTraitement = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $convertiePar = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateConversion = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'geo_position_id', nullable: true, onDelete: 'SET NULL')]
    private ?GeoPosition $geoPosition = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PieceJointe $pieceJointe = null;

    /** @var \Doctrine\Common\Collections\Collection<int, RequeteHistorique>|null */
    #[ORM\OneToMany(targetEntity: RequeteHistorique::class, mappedBy: 'requete', cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'DESC', 'id' => 'DESC'])]
    private ?\Doctrine\Common\Collections\Collection $historique = null;

    public function getCategorieIncident(): \App\Enum\CategorieIncident
    {
        return $this->categorieIncident ?? \App\Enum\CategorieIncident::depuisCategorieExistante($this->categorie);
    }

    public function setCategorieIncident(\App\Enum\CategorieIncident $c): static
    {
        $this->categorieIncident = $c;
        $this->categorie = $c->versCategorieExistante();

        return $this;
    }

    /** Statut détaillé ; pour les requêtes antérieures, déduit du statut historique. */
    public function getStatutTraitement(): \App\Enum\StatutTraitement
    {
        return $this->statutTraitement ?? \App\Enum\StatutTraitement::depuisStatutExistant($this->statut);
    }

    public function setStatutTraitement(\App\Enum\StatutTraitement $s): static
    {
        $this->statutTraitement = $s;
        $this->statut = $s->versStatutExistant();

        return $this;
    }

    public function getUrgenceDeclaree(): ?NiveauUrgence { return $this->urgenceDeclaree; }
    public function setUrgenceDeclaree(?NiveauUrgence $u): static { $this->urgenceDeclaree = $u; return $this; }
    public function isUrgenceAQualifier(): bool { return $this->urgenceAQualifier; }
    public function setUrgenceAQualifier(bool $b): static { $this->urgenceAQualifier = $b; return $this; }
    public function isSoumissionPublique(): bool { return $this->soumissionPublique; }
    public function setSoumissionPublique(bool $b): static { $this->soumissionPublique = $b; return $this; }
    public function getComplementAdresse(): ?string { return $this->complementAdresse; }
    public function setComplementAdresse(?string $c): static { $this->complementAdresse = $c; return $this; }
    public function getCodePostal(): ?string { return $this->codePostal; }
    public function setCodePostal(?string $c): static { $this->codePostal = $c; return $this; }
    public function getVille(): ?string { return $this->ville; }
    public function setVille(?string $v): static { $this->ville = $v; return $this; }
    public function getSecteur(): ?string { return $this->secteur; }
    public function setSecteur(?string $s): static { $this->secteur = $s; return $this; }
    public function getEmailDemandeur(): ?string { return $this->emailDemandeur; }
    public function setEmailDemandeur(?string $e): static { $this->emailDemandeur = $e; return $this; }
    public function getTelephoneDemandeur(): ?string { return $this->telephoneDemandeur; }
    public function setTelephoneDemandeur(?string $t): static { $this->telephoneDemandeur = $t; return $this; }
    public function getConsentementLe(): ?\DateTimeImmutable { return $this->consentementLe; }
    public function setConsentementLe(?\DateTimeImmutable $d): static { $this->consentementLe = $d; return $this; }
    public function getInformationsComplementaires(): ?string { return $this->informationsComplementaires; }
    public function setInformationsComplementaires(?string $i): static { $this->informationsComplementaires = $i; return $this; }
    public function getMotifRejet(): ?string { return $this->motifRejet; }
    public function setMotifRejet(?string $m): static { $this->motifRejet = $m; return $this; }
    public function getTraiteePar(): ?User { return $this->traiteePar; }
    public function setTraiteePar(?User $u): static { $this->traiteePar = $u; return $this; }
    public function getDateDernierTraitement(): ?\DateTimeImmutable { return $this->dateDernierTraitement; }
    public function setDateDernierTraitement(?\DateTimeImmutable $d): static { $this->dateDernierTraitement = $d; return $this; }
    public function getConvertiePar(): ?User { return $this->convertiePar; }
    public function setConvertiePar(?User $u): static { $this->convertiePar = $u; return $this; }
    public function getDateConversion(): ?\DateTimeImmutable { return $this->dateConversion; }
    public function setDateConversion(?\DateTimeImmutable $d): static { $this->dateConversion = $d; return $this; }
    public function getGeoPosition(): ?GeoPosition { return $this->geoPosition; }
    public function setGeoPosition(?GeoPosition $g): static { $this->geoPosition = $g; return $this; }
    public function getPieceJointe(): ?PieceJointe { return $this->pieceJointe; }
    public function setPieceJointe(?PieceJointe $p): static { $this->pieceJointe = $p; return $this; }

    public function getCoordonnees(): ?\App\Geo\Coordonnees
    {
        return $this->geoPosition?->getCoordonnees();
    }

    /** Adresse complète (ligne, complément, code postal, ville). */
    public function getAdresseComplete(): string
    {
        return implode(', ', array_filter([
            $this->adresse,
            $this->complementAdresse,
            trim(($this->codePostal ?? '').' '.($this->ville ?? '')),
        ], static fn (?string $p) => null !== $p && '' !== trim($p)));
    }

    /** @return \Doctrine\Common\Collections\Collection<int, RequeteHistorique> */
    public function getHistorique(): \Doctrine\Common\Collections\Collection
    {
        return $this->historique ??= new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function ajouterHistorique(RequeteHistorique $entree): static
    {
        if (!$this->getHistorique()->contains($entree)) {
            $this->getHistorique()->add($entree);
        }

        return $this;
    }

    // ------------------------------------------------------------------
    // Ajouts (additifs) — signalement par un citoyen connecté (ROLE_CITOYEN).
    // Signalement anonyme : nomDemandeur = « Anonyme », prénom jamais enregistré.
    // ------------------------------------------------------------------

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $prenomDemandeur = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $anonyme = false;

    /** Super-vilain dont la présence est signalée (parmi ceux enregistrés). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Villain $villainSignale = null;

    /** Compte citoyen auteur du signalement (seul autorisé à en consulter la confirmation). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $citoyen = null;

    public function getPrenomDemandeur(): ?string { return $this->prenomDemandeur; }
    public function setPrenomDemandeur(?string $p): static { $this->prenomDemandeur = $p; return $this; }
    public function isAnonyme(): bool { return $this->anonyme; }
    public function setAnonyme(bool $a): static { $this->anonyme = $a; return $this; }
    public function getVillainSignale(): ?Villain { return $this->villainSignale; }
    public function setVillainSignale(?Villain $v): static { $this->villainSignale = $v; return $this; }
    public function getCitoyen(): ?User { return $this->citoyen; }
    public function setCitoyen(?User $u): static { $this->citoyen = $u; return $this; }
}
