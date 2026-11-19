<?php

namespace App\Form;

use App\Entity\RequeteCitoyenne;
use App\Enum\NiveauUrgence;
use App\Enum\OrigineAlerte;
use App\Enum\RequeteCategorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RequeteCitoyenneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('origine', EnumType::class, ['label' => 'Origine de l\'alerte', 'class' => OrigineAlerte::class, 'expanded' => true, 'choice_label' => static fn (OrigineAlerte $o) => $o->label()])
            ->add('titre', TextType::class, ['label' => 'Description succincte des faits', 'empty_data' => '', 'attr' => ['placeholder' => 'Ex : Panache d\'énergie violette au port…']])
            ->add('description', TextareaType::class, ['label' => 'Détails', 'empty_data' => '', 'attr' => ['rows' => 3, 'placeholder' => 'Détails du témoin, nombre d\'otages ou blessés, signature énergétique…']])
            ->add('categorie', EnumType::class, ['label' => 'Catégorie', 'class' => RequeteCategorie::class, 'choice_label' => static fn (RequeteCategorie $c) => $c->label()])
            ->add('adresse', TextType::class, ['label' => 'Localisation & secteur', 'empty_data' => ''])
            ->add('positionX', NumberType::class, ['label' => 'X (km)', 'scale' => 2, 'html5' => true, 'empty_data' => '50', 'attr' => ['min' => 0, 'max' => 100, 'step' => '0.1']])
            ->add('positionY', NumberType::class, ['label' => 'Y (km)', 'scale' => 2, 'html5' => true, 'empty_data' => '50', 'attr' => ['min' => 0, 'max' => 100, 'step' => '0.1']])
            ->add('niveauUrgence', EnumType::class, ['label' => 'Estimation du niveau de menace', 'class' => NiveauUrgence::class, 'expanded' => true, 'choice_label' => static fn (NiveauUrgence $u) => $u->label()])
            ->add('nomDemandeur', TextType::class, ['label' => 'Demandeur', 'empty_data' => '']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RequeteCitoyenne::class]);
    }
}
