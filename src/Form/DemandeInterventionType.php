<?php

namespace App\Form;

use App\Enum\CategorieIncident;
use App\Enum\NiveauUrgence;
use App\Form\Model\DemandeIntervention;
use App\Requete\PieceJointeStorage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/** Formulaire public /demande-intervention (aucun compte requis). */
final class DemandeInterventionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, ['label' => 'Titre de la demande', 'attr' => ['maxlength' => 120, 'placeholder' => 'Ex : Incendie dans un entrepôt', 'autocomplete' => 'off']])
            ->add('description', TextareaType::class, ['label' => 'Description de la situation', 'help' => 'Ce que vous voyez, nombre de personnes concernées, dangers immédiats.', 'attr' => ['rows' => 5, 'maxlength' => 3000]])
            ->add('categorie', EnumType::class, ['label' => 'Type d\'incident', 'class' => CategorieIncident::class, 'placeholder' => 'Choisir…', 'choice_label' => static fn (CategorieIncident $c) => $c->label()])
            ->add('urgence', EnumType::class, ['label' => 'Niveau d\'urgence estimé', 'class' => NiveauUrgence::class, 'expanded' => true, 'choice_label' => static fn (NiveauUrgence $u) => $u->label(), 'help' => 'Votre estimation : l\'urgence officielle est fixée par un opérateur.'])
            ->add('adresse', TextType::class, ['label' => 'Adresse de l\'incident', 'attr' => ['maxlength' => 200, 'autocomplete' => 'street-address', 'placeholder' => 'N° et rue']])
            ->add('complementAdresse', TextType::class, ['label' => 'Complément d\'adresse', 'required' => false, 'attr' => ['maxlength' => 120, 'placeholder' => 'Bâtiment, étage, repère…']])
            ->add('codePostal', TextType::class, ['label' => 'Code postal', 'attr' => ['maxlength' => 5, 'inputmode' => 'numeric', 'autocomplete' => 'postal-code', 'pattern' => '[0-9]{5}|2[AaBb][0-9]{3}']])
            ->add('ville', TextType::class, ['label' => 'Ville', 'attr' => ['maxlength' => 120, 'autocomplete' => 'address-level2']])
            ->add('nom', TextType::class, ['label' => 'Votre nom', 'required' => false, 'help' => 'Facultatif.', 'attr' => ['maxlength' => 120, 'autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'required' => false, 'attr' => ['maxlength' => 180, 'autocomplete' => 'email']])
            ->add('telephone', TelType::class, ['label' => 'Téléphone', 'required' => false, 'attr' => ['maxlength' => 20, 'autocomplete' => 'tel']])
            ->add('pieceJointe', FileType::class, [
                'label' => 'Photo ou document (facultatif)',
                'required' => false,
                'help' => 'JPEG, PNG, WebP ou PDF — 5 Mo maximum.',
                'attr' => ['accept' => '.jpg,.jpeg,.png,.webp,.pdf'],
                'constraints' => [new File(
                    maxSize: PieceJointeStorage::TAILLE_MAX,
                    mimeTypes: array_keys(PieceJointeStorage::TYPES),
                    extensions: PieceJointeStorage::EXTENSIONS,
                    mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP ou PDF.',
                    maxSizeMessage: 'Le fichier dépasse 5 Mo.',
                )],
            ])
            ->add('consentement', CheckboxType::class, ['label' => 'J\'accepte que ces informations soient utilisées pour traiter ma demande et me recontacter si nécessaire.'])
            // Anti-robot (non mappés) : pot de miel invisible + jeton horodaté signé.
            ->add('site_web', TextType::class, ['mapped' => false, 'required' => false, 'label' => 'Ne pas remplir ce champ', 'attr' => ['tabindex' => '-1', 'autocomplete' => 'off'], 'row_attr' => ['class' => 'hp-field', 'aria-hidden' => 'true']])
            ->add('horodatage', HiddenType::class, ['mapped' => false, 'data' => $options['jeton_antibot']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemandeIntervention::class,
            'csrf_token_id' => 'demande_intervention',
            'jeton_antibot' => '',
        ]);
        $resolver->setAllowedTypes('jeton_antibot', 'string');
    }
}
