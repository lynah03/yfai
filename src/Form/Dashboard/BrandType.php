<?php
    
    namespace App\Form\Dashboard;
    
    use App\Entity\Brand;
    use Symfony\Component\Form\AbstractType;
    use Symfony\Component\Form\Extension\Core\Type\FileType;
    use Symfony\Component\Form\Extension\Core\Type\TextareaType;
    use Symfony\Component\Form\Extension\Core\Type\TextType;
    use Symfony\Component\Form\FormBuilderInterface;
    use Symfony\Component\OptionsResolver\OptionsResolver;
    use Symfony\Component\Validator\Constraints\File;
    
    class BrandType extends AbstractType
    {
        public function buildForm(FormBuilderInterface $builder, array $options): void
        {
            $builder
                ->add('name', TextType::class, [
                    'label' => 'Brand Name',
                    'required' => true,
                    'label_attr' => ['class' => 'form-label'],
                    'row_attr'   => ['class' => 'form-group'],
                    'attr' => [
                        'class' => 'form-control my-3',
                        'maxlength' => 255,
                        'placeholder' => 'e.g. Dior',
                    ],
                    'help' => 'Public display name.',
                    'help_attr' => ['class' => 'form-help'],
                ])
                
                ->add('country', TextType::class, [
                    'label' => 'Country',
                    'required' => false,
                    'label_attr' => ['class' => 'form-label'],
                    'row_attr'   => ['class' => 'form-group'],
                    'attr' => [
                        'class' => 'form-control my-3',
                        'placeholder' => 'e.g. France',
                    ],
                    'help' => 'Optional.',
                    'help_attr' => ['class' => 'form-help'],
                ])
                
                ->add('description', TextareaType::class, [
                    'label' => 'Description',
                    'required' => false,
                    'label_attr' => ['class' => 'form-label'],
                    'row_attr'   => ['class' => 'form-group'],
                    'attr' => [
                        'class' => 'form-textarea my-3',
                        'rows'  => 4,
                        'placeholder' => 'Short brand bio…',
                    ],
                    'help' => 'Optional.',
                    'help_attr' => ['class' => 'form-help'],
                ])
                
                ->add('logo', FileType::class, [
                    'label' => 'Brand Logo (PNG/JPEG/GIF, ≤ 2MB)',
                    'mapped' => false,
                    'required' => false,
                    'label_attr' => ['class' => 'form-label'],
                    'row_attr'   => ['class' => 'form-group my-3'],
                    'attr' => [
                        'class' => 'form-control my-3',
                        'accept' => 'image/*',
                    ],
                    'help' => 'Transparent PNG recommended.',
                    'help_attr' => ['class' => 'form-help'],
                    'constraints' => [
                        new File([
                            'maxSize' => '2M',
                            'mimeTypes' => [
                                'image/jpeg',
                                'image/png',
                                'image/gif',
                            ],
                            'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, GIF)',
                        ]),
                    ],
                ]);
        }
        
        public function configureOptions(OptionsResolver $resolver): void
        {
            $resolver->setDefaults([
                'data_class' => Brand::class,
            ]);
        }
    }
