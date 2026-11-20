<?php

namespace App\Form\Model;

use App\Entity\RequeteCitoyenne;
use App\Enum\CategorieIncident;
use App\Enum\NiveauUrgence;
use App\Enum\StatutTraitement;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Qualification d'une requête par un opérateur missions. */
final class QualificationRequete
{
    #[Assert\NotNull]
    public ?NiveauUrgence $urgenceInterne = null;

    #[Assert\NotNull]
    public ?CategorieIncident $categorie = null;

    #[Assert\NotNull]
    #[Assert\Choice(callback: [StatutTraitement::class, 'qualifiables'])]
    public ?StatutTraitement $statut = StatutTraitement::EN_ANALYSE;

    #[Assert\Length(max: 60)]
    public ?string $secteur = null;

    #[Assert\Length(max: 5000)]
    public ?string $informations = null;

    #[Assert\Range(min: -90, max: 90)]
    public ?float $latitude = null;

    #[Assert\Range(min: -180, max: 180)]
    public ?float $longitude = null;

    /** Relancer le géocodage de l'adresse saisie. */
    public bool $regeocoder = false;

    #[Assert\Length(max: 1000)]
    public ?string $commentaire = null;

    public static function depuis(RequeteCitoyenne $r): self
    {
        $q = new self();
        $q->urgenceInterne = $r->getNiveauUrgence();
        $q->categorie = $r->getCategorieIncident();
        $q->statut = \in_array($r->getStatutTraitement(), StatutTraitement::qualifiables(), true) ? $r->getStatutTraitement() : StatutTraitement::EN_ANALYSE;
        $q->secteur = $r->getSecteur();
        $q->informations = $r->getInformationsComplementaires();
        $c = $r->getCoordonnees();
        $q->latitude = $c?->latitude;
        $q->longitude = $c?->longitude;

        return $q;
    }

    #[Assert\Callback]
    public function validerCoordonnees(ExecutionContextInterface $context): void
    {
        if ((null === $this->latitude) !== (null === $this->longitude)) {
            $context->buildViolation('Renseignez la latitude et la longitude, ou aucune des deux.')->atPath('latitude')->addViolation();
        }
    }
}
