<?php

namespace App\Form;

use App\Entity\User;
use App\Security\RoleCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création / modification d'un compte interne par un administrateur.
 * Le type de compte est choisi dans RoleCatalog::ATTRIBUABLES (liste contrôlée côté serveur :
 * ChoiceType rejette toute valeur hors liste). Un seul type de compte par utilisateur.
 */
final class CompteOperateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'E-mail (identifiant)', 'empty_data' => '', 'attr' => ['autocomplete' => 'off']])
            ->add('nom', TextType::class, ['label' => 'Nom affiché', 'empty_data' => ''])
            ->add('fonction', TextType::class, ['label' => 'Fonction', 'required' => false])
            ->add('typeCompte', ChoiceType::class, [
                'label' => 'Type de compte',
                'mapped' => false,
                'choices' => RoleCatalog::choix(),
                'expanded' => true,
                'required' => $options['creation'],
                'placeholder' => $options['creation'] ? false : 'Conserver les rôles actuels',
                'data' => $options['type_actuel'],
                'constraints' => $options['creation'] ? [new Assert\NotBlank(message: 'Choisissez un type de compte.')] : [],
            ])
            ->add('actif', CheckboxType::class, ['label' => 'Compte actif', 'required' => false]);

        if ($options['creation']) {
            $builder->add('plainPassword', RepeatedType::class, self::motDePasse());
        }
    }

    /** @return array<string, mixed> */
    public static function motDePasse(): array
    {
        return [
            'type' => PasswordType::class,
            'mapped' => false,
            'first_options' => ['label' => 'Mot de passe', 'attr' => ['autocomplete' => 'new-password'], 'help' => '12 caractères minimum.'],
            'second_options' => ['label' => 'Confirmation', 'attr' => ['autocomplete' => 'new-password']],
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 12, max: 4096)],
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'creation' => true, 'type_actuel' => null]);
        $resolver->setAllowedTypes('creation', 'bool');
        $resolver->setAllowedTypes('type_actuel', ['null', 'string']);
    }
}
