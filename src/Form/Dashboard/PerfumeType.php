<?php

namespace App\Form\Dashboard;

use App\Entity\Brand;
use App\Entity\Perfume;
use App\Enum\Concentration;
use App\Enum\MarketingGender;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class PerfumeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Perfume Name',
                'required' => true,
                'attr' => [
                    'class' => 'form-control my-3',
                    'maxlength' => 180,
                    'placeholder' => 'e.g. Nero',
                ],
            ])

            ->add('brand', EntityType::class, [
                'label' => 'Brand',
                'class' => Brand::class,
                'choice_label' => 'name',
                'required' => true,
                'attr' => [
                    'class' => 'form-control my-3',
                ],
            ])

            ->add('shortDescription', TextType::class, [
                'label' => 'Short Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control my-3',
                    'maxlength' => 255,
                    'placeholder' => 'e.g. Dark, smoky and magnetic.',
                ],
                'help' => 'Short luxury sentence used in the AI Scent Concierge results.',
                'help_attr' => [
                    'class' => 'form-help text-xs text-gray-500',
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Full Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea my-3',
                    'rows' => 6,
                    'placeholder' => 'Longer product description...',
                ],
            ])

            ->add('imageFile', FileType::class, [
                'label' => 'Product Image',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control my-3',
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
                'help' => 'Recommended: premium product image, PNG/JPEG/WebP, max 4MB.',
                'help_attr' => [
                    'class' => 'form-help text-xs text-gray-500',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '4M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file: JPEG, PNG or WebP.',
                    ]),
                ],
            ])

            ->add('productUrl', UrlType::class, [
                'label' => 'Product URL',
                'required' => false,
                'attr' => [
                    'class' => 'form-control my-3',
                    'maxlength' => 500,
                    'placeholder' => 'https://maisoncipro.com/products/nero',
                ],
                'help' => 'Used as the CTA link in the AI Scent Concierge.',
                'help_attr' => [
                    'class' => 'form-help text-xs text-gray-500',
                ],
            ])

            ->add('releaseYear', IntegerType::class, [
                'label' => 'Release Year',
                'required' => false,
                'attr' => [
                    'class' => 'form-control my-3',
                    'min' => 1900,
                    'max' => 2100,
                    'placeholder' => 'e.g. 2026',
                ],
            ])

            ->add('concentration', EnumType::class, [
                'label' => 'Concentration',
                'class' => Concentration::class,
                'choice_label' => 'value',
                'required' => false,
                'placeholder' => 'Choose a concentration',
                'attr' => [
                    'class' => 'form-control my-3',
                ],
            ])

            ->add('marketingGender', EnumType::class, [
                'label' => 'Marketing Gender',
                'class' => MarketingGender::class,
                'choice_label' => 'value',
                'required' => false,
                'placeholder' => 'Choose a marketing gender',
                'attr' => [
                    'class' => 'form-control my-3',
                ],
            ])

            ->add('listPriceCents', IntegerType::class, [
                'label' => 'List Price Cents',
                'required' => false,
                'attr' => [
                    'class' => 'form-control my-3',
                    'min' => 0,
                    'step' => 1,
                    'placeholder' => '26000',
                ],
                'help' => 'Price stored in cents. Example: 260.00 EUR = 26000.',
                'help_attr' => [
                    'class' => 'form-help text-xs text-gray-500',
                ],
            ])

            ->add('listPriceCurrency', TextType::class, [
                'label' => 'Currency',
                'required' => true,
                'empty_data' => 'EUR',
                'attr' => [
                    'class' => 'form-control my-3',
                    'maxlength' => 3,
                    'placeholder' => 'EUR',
                ],
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