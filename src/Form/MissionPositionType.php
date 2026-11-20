<?php

namespace App\Form;

use App\Form\Model\MissionPosition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MissionPositionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adresse', TextType::class, ['label' => 'Adresse', 'required' => false])
            ->add('codePostal', TextType::class, ['label' => 'Code postal', 'required' => false])
            ->add('ville', TextType::class, ['label' => 'Ville', 'required' => false])
            ->add('latitude', NumberType::class, ['label' => 'Latitude', 'required' => false, 'scale' => 7, 'html5' => true, 'help' => 'Laisser vide pour géocoder l\'adresse.', 'attr' => ['step' => '0.0000001', 'min' => -90, 'max' => 90]])
            ->add('longitude', NumberType::class, ['label' => 'Longitude', 'required' => false, 'scale' => 7, 'html5' => true, 'attr' => ['step' => '0.0000001', 'min' => -180, 'max' => 180]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MissionPosition::class, 'csrf_token_id' => 'mission_position']);
    }
}
