<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WebContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class,[
                'attr'=>[
                    'placeholder'=>'First Name'
                ]
            ])
            ->add('lastname', TextType::class,[
                'attr'=>[
                    'placeholder'=>'Last Name'
                ]
            ])
            ->add('company', TextType::class,[
                'attr'=>[
                    'placeholder'=>'Company'
                ]
            ])
            ->add('phone', TelType::class,[
                'attr'=>[
                    'placeholder'=>'Phone'
                ]
            ])
            ->add('email', EmailType::class,[
                'attr'=>[
                    'placeholder'=>'Email'
                ]
            ])
            ->add('message',TextareaType::class,[
                'attr'=>[
                    'placeholder'=>'Message',
                    'rows' => 5,
                ]
            ])
            ->add('submit',SubmitType::class,[
                'label' => 'Send request',
                'attr'=>[
                    'class'=>'contact-submit'
                ]
                
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}
