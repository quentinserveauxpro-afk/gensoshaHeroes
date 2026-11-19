<?php

namespace App\Form;

use App\Entity\Aptitude;
use App\Entity\Mission;
use App\Entity\Villain;
use App\Enum\Difficulte;
use App\Enum\NiveauUrgence;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class MissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $requis = static fn (string $label, ?string $vide = null) => [
            'label' => $label, 'required' => false, 'attr' => ['min' => 0, 'max' => 100],
        ] + (null !== $vide ? ['empty_data' => $vide] : []);

        $builder
            ->add('nom', TextType::class, ['label' => 'Titre', 'empty_data' => '', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('description', TextareaType::class, ['label' => 'Description', 'empty_data' => '', 'constraints' => [new Assert\NotBlank()]])
            ->add('difficulte', EnumType::class, ['label' => 'Difficulté', 'class' => Difficulte::class, 'choice_label' => static fn (Difficulte $d) => $d->label()])
            ->add('niveauUrgence', EnumType::class, ['label' => 'Urgence', 'class' => NiveauUrgence::class, 'choice_label' => static fn (NiveauUrgence $u) => $u->label()])
            ->add('localisation', TextType::class, ['label' => 'Localisation', 'required' => false])
            ->add('positionX', NumberType::class, ['label' => 'Position X (km)', 'scale' => 2, 'html5' => true, 'empty_data' => '50', 'attr' => ['min' => 0, 'max' => 100, 'step' => '0.1']])
            ->add('positionY', NumberType::class, ['label' => 'Position Y (km)', 'scale' => 2, 'html5' => true, 'empty_data' => '50', 'attr' => ['min' => 0, 'max' => 100, 'step' => '0.1']])
            ->add('dateDebut', DateTimeType::class, ['label' => 'Début', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('dateFinPrevue', DateTimeType::class, ['label' => 'Fin prévue (échéance du timer)', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'constraints' => [new Assert\NotNull()]])
            ->add('tailleEquipeMinimale', IntegerType::class, ['label' => 'Équipe : taille minimale', 'empty_data' => '1', 'attr' => ['min' => 1, 'max' => 12]])
            ->add('tailleEquipeMaximale', IntegerType::class, ['label' => 'Équipe : taille maximale', 'empty_data' => '4', 'attr' => ['min' => 1, 'max' => 12]])
            ->add('seuilSanteMinimal', IntegerType::class, ['label' => 'Santé minimale exigée (%)', 'empty_data' => '30', 'attr' => ['min' => 0, 'max' => 100]])
            ->add('combatRequis', IntegerType::class, $requis('Combat minimal'))
            ->add('intelligenceRequis', IntegerType::class, $requis('Intelligence minimale'))
            // Le setter existant setCharismeRequis(int) n'accepte pas null.
            ->add('charismeRequis', IntegerType::class, $requis('Charisme minimal', '0'))
            ->add('mobiliteRequis', IntegerType::class, $requis('Mobilité minimale'))
            ->add('vigueurRequis', IntegerType::class, $requis('Vigueur / résistance minimale'))
            ->add('cible', TextType::class, ['label' => 'Cible', 'required' => false])
            ->add('etatCible', TextType::class, ['label' => 'État de la cible', 'required' => false])
            ->add('villains', EntityType::class, ['label' => 'Vilains impliqués', 'class' => Villain::class, 'choice_label' => 'nom', 'multiple' => true, 'expanded' => true, 'required' => false])
            ->add('aptitudesRequises', EntityType::class, ['label' => 'Aptitudes / immunités obligatoires', 'class' => Aptitude::class, 'choice_label' => 'nom', 'multiple' => true, 'expanded' => true, 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Mission::class]);
    }
}
