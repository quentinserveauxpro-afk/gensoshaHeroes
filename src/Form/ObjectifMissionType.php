<?php

namespace App\Form;

use App\Entity\ObjectifMission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ObjectifMissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, ['label' => 'Objectif', 'empty_data' => ''])
            ->add('description', TextType::class, ['label' => 'Détail', 'required' => false])
            ->add('progression', RangeType::class, ['label' => 'Progression (%)', 'empty_data' => '0', 'attr' => ['min' => 0, 'max' => 100, 'step' => 5]])
            ->add('ordreAffichage', IntegerType::class, ['label' => 'Ordre', 'empty_data' => '0', 'attr' => ['min' => 0]])
            ->add('obligatoire', CheckboxType::class, ['label' => 'Obligatoire', 'required' => false])
            ->add('termine', CheckboxType::class, ['label' => 'Terminé', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ObjectifMission::class]);
    }
}
