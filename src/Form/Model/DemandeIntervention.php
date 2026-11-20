<?php

namespace App\Form\Model;

use App\Enum\CategorieIncident;
use App\Enum\NiveauUrgence;
use App\Geo\SecteurResolver;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Données du formulaire public de demande d'intervention (validation serveur).
 * Objet distinct de l'entité : le nom du demandeur y est facultatif, et seules les
 * données nécessaires au traitement sont recopiées dans RequeteCitoyenne.
 */
final class DemandeIntervention
{
    #[Assert\NotBlank(message: 'Indiquez un titre.')]
    #[Assert\Length(min: 5, max: 120)]
    public ?string $titre = null;

    #[Assert\NotBlank(message: 'Décrivez la situation.')]
    #[Assert\Length(min: 20, max: 3000)]
    public ?string $description = null;

    #[Assert\NotNull(message: 'Choisissez une catégorie.')]
    public ?CategorieIncident $categorie = null;

    #[Assert\NotNull(message: 'Estimez le niveau d\'urgence.')]
    public ?NiveauUrgence $urgence = null;

    #[Assert\NotBlank(message: 'Indiquez l\'adresse de l\'incident.')]
    #[Assert\Length(max: 200)]
    public ?string $adresse = null;

    #[Assert\Length(max: 120)]
    public ?string $complementAdresse = null;

    #[Assert\NotBlank(message: 'Indiquez le code postal.')]
    #[Assert\Regex(pattern: SecteurResolver::REGEX_CODE_POSTAL, message: 'Code postal français invalide.')]
    public ?string $codePostal = null;

    #[Assert\NotBlank(message: 'Indiquez la ville.')]
    #[Assert\Length(max: 120)]
    public ?string $ville = null;

    #[Assert\Length(max: 120)]
    public ?string $nom = null;

    #[Assert\Email(message: 'Adresse e-mail invalide.')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Regex(pattern: '/^\+?[0-9 .()-]{6,20}$/', message: 'Numéro de téléphone invalide.')]
    public ?string $telephone = null;

    #[Assert\IsTrue(message: 'Votre consentement est nécessaire pour traiter la demande.')]
    public bool $consentement = false;

    public ?UploadedFile $pieceJointe = null;

    #[Assert\Callback]
    public function validerContact(ExecutionContextInterface $context): void
    {
        if (null === $this->email && null === $this->telephone) {
            $context->buildViolation('Indiquez un e-mail ou un téléphone pour pouvoir être recontacté.')
                ->atPath('email')->addViolation();
        }
    }
}
