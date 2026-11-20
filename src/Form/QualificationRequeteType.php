<?php

namespace App\Form;

use App\Enum\CategorieIncident;
use App\Enum\NiveauUrgence;
use App\Enum\StatutTraitement;
use App\Form\Model\QualificationRequete;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class QualificationRequeteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('statut', EnumType::class, ['label' => 'Statut après qualification', 'class' => StatutTraitement::class, 'choices' => StatutTraitement::qualifiables(), 'choice_label' => static fn (StatutTraitement $s) => $s->label()])
            ->add('urgenceInterne', EnumType::class, ['label' => 'Urgence interne (officielle)', 'class' => NiveauUrgence::class, 'choice_label' => static fn (NiveauUrgence $u) => $u->label()])
            ->add('categorie', EnumType::class, ['label' => 'Catégorie', 'class' => CategorieIncident::class, 'choice_label' => static fn (CategorieIncident $c) => $c->label()])
            ->add('secteur', TextType::class, ['label' => 'Secteur', 'required' => false])
            ->add('informations', TextareaType::class, ['label' => 'Informations complémentaires', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('latitude', NumberType::class, ['label' => 'Latitude', 'required' => false, 'scale' => 7, 'html5' => true, 'attr' => ['step' => '0.0000001', 'min' => -90, 'max' => 90]])
            ->add('longitude', NumberType::class, ['label' => 'Longitude', 'required' => false, 'scale' => 7, 'html5' => true, 'attr' => ['step' => '0.0000001', 'min' => -180, 'max' => 180]])
            ->add('regeocoder', CheckboxType::class, ['label' => 'Relancer le géocodage de l\'adresse', 'required' => false])
            ->add('commentaire', TextareaType::class, ['label' => 'Commentaire (historique)', 'required' => false, 'attr' => ['rows' => 2]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => QualificationRequete::class, 'csrf_token_id' => 'qualification_requete']);
    }
}
