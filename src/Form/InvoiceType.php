<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Invoice;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];

        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'label' => 'Client',
                'choices' => $user->getClients(),
                'placeholder' => 'Sélectionner un client',
            ])
            ->add('createdAt', DateType::class, [
                'label' => 'Date de la facture',
                'widget' => 'single_text',
                'data' => new \DateTimeImmutable(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invoice::class,
            'user' => null,
        ]);
    }
}