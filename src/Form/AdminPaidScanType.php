<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Scan;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class AdminPaidScanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'label' => 'Gebruiker',
                'placeholder' => 'Kies een gebruiker',
                'choice_label' => 'email',
                'query_builder' => fn (UserRepository $users) => $users->createQueryBuilder('u')->orderBy('u.email', 'ASC'),
            ])
            ->add('websiteUrl', UrlType::class, [
                'label' => 'Website-URL',
                'constraints' => [
                    new NotBlank(message: 'Vul een website-URL in.'),
                    new Length(max: 255, maxMessage: 'De URL mag maximaal {{ limit }} tekens lang zijn.'),
                    new Url(message: 'Vul een geldige URL in.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Scan::class,
        ]);
    }
}
