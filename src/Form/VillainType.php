<?php

namespace App\Form;

use App\Entity\Villain;
use App\Enum\VillainStatut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class VillainType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom', 'empty_data' => ''])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('niveauMenace', IntegerType::class, ['label' => 'Niveau de menace (1-10)', 'empty_data' => '1', 'attr' => ['min' => 1, 'max' => 10]])
            ->add('combat', IntegerType::class, ['label' => 'Combat', 'empty_data' => '0', 'attr' => ['min' => 0, 'max' => 100]])
            ->add('intelligence', IntegerType::class, ['label' => 'Intelligence', 'empty_data' => '0', 'attr' => ['min' => 0, 'max' => 100]])
            ->add('image', TextType::class, ['label' => 'Image (chemin ou URL)', 'required' => false])
            ->add('statut', EnumType::class, ['label' => 'Statut', 'class' => VillainStatut::class, 'choice_label' => static fn (VillainStatut $s) => $s->label()]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Villain::class]);
    }
}
