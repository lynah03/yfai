<?php

namespace App\Form\Dashboard;

use App\Entity\Note;
use App\Entity\Perfume;
use App\Entity\PerfumeNote;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PerfumeNoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('layer')
            ->add('intensity')
            ->add('perfume', EntityType::class, [
                'class' => Perfume::class,
                'choice_label' => 'id',
            ])
            ->add('note', EntityType::class, [
                'class' => Note::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PerfumeNote::class,
        ]);
    }
}
