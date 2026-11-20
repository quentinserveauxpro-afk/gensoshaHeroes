<?php

namespace App\Entity;

use App\Enum\StatutTraitement;
use App\Repository\RequeteHistoriqueRepository;
use Doctrine\ORM\Mapping as ORM;

/** Entrée immuable de l'historique de traitement d'une requête citoyenne. */
#[ORM\Entity(repositoryClass: RequeteHistoriqueRepository::class)]
#[ORM\Index(columns: ['created_at'], name: 'idx_requete_historique_date')]
class RequeteHistorique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'historique')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RequeteCitoyenne $requete;

    /** Opérateur auteur de l'action (null : soumission publique ou compte supprimé). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $operateur = null;

    #[ORM\Column(length: 40)]
    private string $action;

    #[ORM\Column(enumType: StatutTraitement::class, length: 30, nullable: true)]
    private ?StatutTraitement $ancienStatut = null;

    #[ORM\Column(enumType: StatutTraitement::class, length: 30)]
    private StatutTraitement $nouveauStatut;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        RequeteCitoyenne $requete,
        string $action,
        ?StatutTraitement $ancienStatut,
        StatutTraitement $nouveauStatut,
        ?User $operateur,
        ?string $commentaire,
        \DateTimeImmutable $createdAt,
    ) {
        $this->requete = $requete;
        $this->action = $action;
        $this->ancienStatut = $ancienStatut;
        $this->nouveauStatut = $nouveauStatut;
        $this->operateur = $operateur;
        $this->commentaire = $commentaire;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getRequete(): RequeteCitoyenne { return $this->requete; }
    public function getOperateur(): ?User { return $this->operateur; }
    public function getAction(): string { return $this->action; }
    public function getAncienStatut(): ?StatutTraitement { return $this->ancienStatut; }
    public function getNouveauStatut(): StatutTraitement { return $this->nouveauStatut; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
