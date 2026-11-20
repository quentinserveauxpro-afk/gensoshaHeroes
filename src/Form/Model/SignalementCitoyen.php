<?php

namespace App\Form\Model;

use App\Entity\Villain;
use App\Enum\CategorieIncident;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Données du signalement d'incident par un citoyen connecté (validation serveur).
 * Objet distinct de l'entité : l'identité n'est recopiée dans RequeteCitoyenne que si le
 * citoyen ne demande pas l'anonymat, et le super-vilain que si sa présence est signalée.
 */
final class SignalementCitoyen
{
    /** Code postal français : exactement 5 chiffres. */
    public const REGEX_CODE_POSTAL = '/^\d{5}$/';
    public const DESCRIPTION_MAX = 2000;

    public bool $anonyme = false;

    #[Assert\Length(max: 120, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $nom = null;

    #[Assert\Length(max: 120, maxMessage: 'Le prénom ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $prenom = null;

    #[Assert\NotBlank(message: 'Indiquez l\'adresse de l\'incident.')]
    #[Assert\Length(max: 200, maxMessage: 'L\'adresse ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $adresse = null;

    #[Assert\NotBlank(message: 'Indiquez le code postal.')]
    #[Assert\Regex(pattern: self::REGEX_CODE_POSTAL, message: 'Le code postal doit contenir exactement 5 chiffres.')]
    public ?string $codePostal = null;

    #[Assert\NotBlank(message: 'Indiquez la ville.')]
    #[Assert\Length(max: 120, maxMessage: 'La ville ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $ville = null;

    #[Assert\NotNull(message: 'Choisissez la catégorie de l\'incident.')]
    public ?CategorieIncident $categorie = null;

    public bool $presenceVilain = false;

    public ?Villain $villain = null;

    #[Assert\Length(max: self::DESCRIPTION_MAX, maxMessage: 'La description ne doit pas dépasser {{ limit }} caractères.')]
    public ?string $description = null;

    #[Assert\Callback]
    public function validerIdentiteEtVilain(ExecutionContextInterface $context): void
    {
        if (!$this->anonyme) {
            if (null === $this->nom || '' === trim($this->nom)) {
                $context->buildViolation('Indiquez votre nom, ou cochez « Rester anonyme ».')->atPath('nom')->addViolation();
            }
            if (null === $this->prenom || '' === trim($this->prenom)) {
                $context->buildViolation('Indiquez votre prénom, ou cochez « Rester anonyme ».')->atPath('prenom')->addViolation();
            }
        }
        if ($this->presenceVilain && null === $this->villain) {
            $context->buildViolation('Sélectionnez le super-vilain aperçu, ou décochez « Présence d\'un super-vilain ».')->atPath('villain')->addViolation();
        }
    }
}
