<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Priority;
use App\Entity\Ticket;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'attr' => ['class' => 'form-control']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 4]
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'label',
                'label' => 'Category',
                'placeholder' => 'Choose a category',
                'attr' => ['class' => 'form-select']
            ])
            ->add('priority', EntityType::class, [
                'class' => Priority::class,
                'choice_label' => 'label',
                'label' => 'Priority',
                'placeholder' => 'Choose a priority',
                'attr' => ['class' => 'form-select']
            ])
            ->add('attachments', FileType::class, [
                'label' => 'Attachments',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.png,.jpg,.jpeg,.pdf,.txt,.doc,.docx'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ticket::class,
        ]);
    }
}
