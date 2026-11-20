<?php

namespace App\Form;

use App\Entity\Villain;
use App\Enum\CategorieIncident;
use App\Form\Model\SignalementCitoyen;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Signalement d'incident par un citoyen (ROLE_CITOYEN). Protégé par CSRF.
 * Les champs conditionnels (identité, super-vilain) sont masqués par le contrôleur Stimulus
 * « signalement » ; sans JavaScript ils restent visibles et le serveur applique les mêmes règles.
 */
final class SignalementCitoyenType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('anonyme', CheckboxType::class, [
                'label' => 'Rester anonyme',
                'required' => false,
                'help' => 'Votre nom et votre prénom ne seront pas enregistrés.',
                'attr' => ['data-signalement-target' => 'anonyme', 'data-action' => 'signalement#basculer'],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => ['maxlength' => 120, 'autocomplete' => 'family-name'],
                'row_attr' => ['data-signalement-target' => 'identite'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => ['maxlength' => 120, 'autocomplete' => 'given-name'],
                'row_attr' => ['data-signalement-target' => 'identite'],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'attr' => ['maxlength' => 200, 'autocomplete' => 'street-address', 'placeholder' => 'N° et rue'],
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'help' => '5 chiffres.',
                'attr' => ['maxlength' => 5, 'minlength' => 5, 'inputmode' => 'numeric', 'pattern' => '[0-9]{5}', 'autocomplete' => 'postal-code'],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'attr' => ['maxlength' => 120, 'autocomplete' => 'address-level2'],
            ])
            ->add('categorie', EnumType::class, [
                'label' => 'Catégorie de l\'incident',
                'class' => CategorieIncident::class,
                'placeholder' => 'Choisir…',
                'choice_label' => static fn (CategorieIncident $c) => $c->label(),
            ])
            ->add('presenceVilain', CheckboxType::class, [
                'label' => 'Présence d\'un super-vilain',
                'required' => false,
                'attr' => ['data-signalement-target' => 'presenceVilain', 'data-action' => 'signalement#basculer'],
            ])
            ->add('villain', EntityType::class, [
                'label' => 'Super-vilain aperçu',
                'class' => Villain::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisir…',
                'required' => false,
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('v')->orderBy('v.nom', 'ASC'),
                'invalid_message' => 'Ce super-vilain n\'existe pas.',
                'row_attr' => ['data-signalement-target' => 'vilain'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'help' => sprintf('Facultatif — %d caractères maximum.', SignalementCitoyen::DESCRIPTION_MAX),
                'attr' => ['rows' => 5, 'maxlength' => SignalementCitoyen::DESCRIPTION_MAX, 'autocomplete' => 'off'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SignalementCitoyen::class,
            'csrf_protection' => true,
            'csrf_token_id' => 'signalement_citoyen',
        ]);
    }
}
