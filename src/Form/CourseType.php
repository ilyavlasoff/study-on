<?php

namespace App\Form;

use App\Model\Mapping\CourseTypeMapping;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('code', TextType::class, [
                'constraints' => [
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Maximum code length is {{ limit }} symbols',
                    ]),
                    new NotBlank([
                        'message' => 'Code can not be empty',
                    ]),
                ],
            ])
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Name can not be empty',
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Maximum name length is {{ limit }} symbols',
                    ]),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Бесплатный' => 'free',
                    'Аренда' => 'rent',
                    'Приобретение' => 'buy',
                ],
            ])
            ->add('price', TextType::class, [
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Type([
                        'type' => 'numeric',
                        'message' => 'Expected type {{ type }}, got {{ value }}',
                    ]),
                ],
            ])
            ->add('rentTime', DateIntervalType::class, [
                'required' => false,
                'empty_data' => null,
                'with_years' => false,
                'with_months' => true,
                'with_days' => true,
                'with_hours' => true,
                'widget' => 'choice',
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Maximum description length is {{ limit }} symbols',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => CourseTypeMapping::class,
        ]);
    }
}
