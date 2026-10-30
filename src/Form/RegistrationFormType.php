<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'E-mailadres',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Wachtwoord',
                    'attr' => ['autocomplete' => 'new-password'],
                    'constraints' => [
                        new NotBlank(message: 'Vul een wachtwoord in.'),
                        new Length(
                            min: 12,
                            minMessage: 'Je wachtwoord moet minimaal {{ limit }} tekens lang zijn.',
                            max: 4096,
                        ),
                        new PasswordStrength(),
                        new NotCompromisedPassword(skipOnError: true),
                    ],
                ],
                'second_options' => [
                    'label' => 'Herhaal wachtwoord',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'De wachtwoorden moeten gelijk zijn.',
                // Read and hashed in the controller, not set on the User directly
                'mapped' => false,
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'Ik ga akkoord met de voorwaarden.',
                'constraints' => [
                    new IsTrue(message: 'Je moet akkoord gaan met de voorwaarden.'),
                ],
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
