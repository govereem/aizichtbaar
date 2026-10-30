<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['autocomplete' => 'name'],
                'constraints' => [
                    new NotBlank(message: 'Vul je naam in.'),
                    new Length(max: 100, maxMessage: 'Je naam mag maximaal {{ limit }} tekens bevatten.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(message: 'Vul je e-mailadres in.'),
                    new Email(message: 'Vul een geldig e-mailadres in.'),
                    new Length(max: 180, maxMessage: 'Je e-mailadres mag maximaal {{ limit }} tekens bevatten.'),
                ],
            ])
            ->add('message', TextareaType::class, [
                'attr' => ['rows' => 6],
                'constraints' => [
                    new NotBlank(message: 'Vul je vraag of opmerking in.'),
                    new Length(
                        min: 10,
                        max: 5000,
                        minMessage: 'Je bericht moet minimaal {{ limit }} tekens bevatten.',
                        maxMessage: 'Je bericht mag maximaal {{ limit }} tekens bevatten.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
