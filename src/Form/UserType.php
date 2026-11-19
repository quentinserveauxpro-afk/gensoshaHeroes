<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'E-mail', 'empty_data' => ''])
            ->add('nom', TextType::class, ['label' => 'Nom affiché', 'empty_data' => ''])
            ->add('fonction', TextType::class, ['label' => 'Fonction', 'required' => false])
            ->add('roles', ChoiceType::class, ['label' => 'Rôles', 'choices' => User::ROLES, 'multiple' => true, 'expanded' => true])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['mot_de_passe_requis'] ? 'Mot de passe' : 'Nouveau mot de passe (laisser vide pour conserver)',
                'mapped' => false,
                'required' => $options['mot_de_passe_requis'],
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => array_filter([
                    $options['mot_de_passe_requis'] ? new Assert\NotBlank() : null,
                    new Assert\Length(min: 8, max: 4096),
                ]),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'mot_de_passe_requis' => true]);
        $resolver->setAllowedTypes('mot_de_passe_requis', 'bool');
    }
}
