<?php

namespace App\Form\Dashboard;

use App\Entity\Brand;
use App\Entity\Perfume;
use App\Enum\MarketingGender;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class PerfumeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('releaseYear', null, [
                'attr' => ['min' => 1900, 'max' => (int)date('Y')],
            ])
            ->add('concentration',EnumType::class, [
                'class' => \App\Enum\Concentration::class,
                'choice_label' => 'value',
            ])
            ->add('marketingGender',EnumType::class, [
                'class' => MarketingGender::class,
                'choice_label' => 'value',
            ])
            ->add('description',TextareaType::class, [
                'attr' => ['rows' => 6],
            ])
            ->add('listPriceCents',NumberType::class, [
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'step' => 0.01],
            ])
            ->add('listPriceCurrency')
            ->add('brand', EntityType::class, [
                'class' => Brand::class,
                'choice_label' => 'name',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Perfume::class,
        ]);
    }
}
