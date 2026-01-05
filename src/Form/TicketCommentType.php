<?php

namespace App\Form;

use App\Entity\TicketComment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TicketCommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('content', TextareaType::class, [
            'label' => 'Message',
            'required' => true,
            'attr' => [
                'class' => 'textarea',
                'rows' => 4,
                'placeholder' => 'Add a reply or update...',
            ],
        ]);

        if ($options['allow_internal']) {
            $builder->add('isInternal', CheckboxType::class, [
                'label' => 'Internal note (hidden from client)',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TicketComment::class,
            'allow_internal' => false,
        ]);

        $resolver->setAllowedTypes('allow_internal', 'bool');
    }
}
