<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix unitaire (€)',
                'scale' => 2,
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'Quantité',
            ])
            ->add('unit', ChoiceType::class, [
                'label' => 'Unité',
                'choices' => [
                    'Heure' => 'hour',
                    'Jour' => 'day',
                    'Pièce' => 'piece',
                    'Mois' => 'month',
                    'Année' => 'year',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}