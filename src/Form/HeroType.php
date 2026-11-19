<?php

namespace App\Form;

use App\Entity\Aptitude;
use App\Entity\Hero;
use App\Enum\HeroRole;
use App\Enum\HeroStatut;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class HeroType extends AbstractType
{
    public const RANGS = ['Alpha' => 'ALPHA', 'Bêta' => 'BETA', 'Oméga' => 'OMEGA'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $stat = static fn (string $label) => [
            'label' => $label,
            'empty_data' => '0',
            'attr' => ['min' => Hero::STAT_MIN, 'max' => Hero::STAT_MAX],
        ];

        $builder
            ->add('nom', TextType::class, ['label' => 'Nom de code', 'empty_data' => '', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('alias', TextType::class, ['label' => 'Identité civile', 'required' => false])
            ->add('rang', ChoiceType::class, ['label' => 'Rang', 'choices' => self::RANGS, 'constraints' => [new Assert\NotBlank()]])
            ->add('role', EnumType::class, ['label' => 'Rôle', 'class' => HeroRole::class, 'choice_label' => static fn (HeroRole $r) => $r->label()])
            ->add('statut', EnumType::class, ['label' => 'Statut', 'class' => HeroStatut::class, 'choice_label' => static fn (HeroStatut $s) => $s->label()])
            ->add('specialite', TextType::class, ['label' => 'Spécialité', 'required' => false])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('niveau', IntegerType::class, ['label' => 'Niveau', 'empty_data' => '1', 'attr' => ['min' => 1, 'max' => 100]])
            ->add('xp', IntegerType::class, ['label' => 'Expérience (niveau courant)', 'empty_data' => '0', 'attr' => ['min' => 0]])
            ->add('pointsDeVie', IntegerType::class, ['label' => 'Points de vie', 'empty_data' => '0', 'attr' => ['min' => 0]])
            ->add('pointsDeVieMaximum', IntegerType::class, ['label' => 'Points de vie maximum', 'empty_data' => '100', 'attr' => ['min' => 1]])
            ->add('fatigue', IntegerType::class, ['label' => 'Fatigue (%)', 'empty_data' => '0', 'attr' => ['min' => 0, 'max' => 100]])
            ->add('combat', IntegerType::class, $stat('Combat'))
            ->add('intelligence', IntegerType::class, $stat('Intelligence'))
            ->add('charisme', IntegerType::class, $stat('Charisme'))
            ->add('mobilite', IntegerType::class, $stat('Mobilité'))
            ->add('vigueur', IntegerType::class, $stat('Vigueur'))
            ->add('positionX', NumberType::class, ['label' => 'Position X (km)', 'scale' => 2, 'empty_data' => '50', 'html5' => true, 'attr' => ['min' => 0, 'max' => 100, 'step' => '0.1'], 'constraints' => [new Assert\Range(min: 0, max: 100)]])
            ->add('positionY', NumberType::class, ['label' => 'Position Y (km)', 'scale' => 2, 'empty_data' => '50', 'html5' => true, 'attr' => ['min' => 0, 'max' => 100, 'step' => '0.1'], 'constraints' => [new Assert\Range(min: 0, max: 100)]])
            ->add('secteur', TextType::class, ['label' => 'Secteur', 'required' => false])
            ->add('image', TextType::class, ['label' => 'Portrait (chemin ou URL)', 'required' => false])
            ->add('aptitudes', EntityType::class, ['label' => 'Aptitudes & immunités', 'class' => Aptitude::class, 'choice_label' => 'nom', 'multiple' => true, 'expanded' => true, 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Hero::class]);
    }
}
