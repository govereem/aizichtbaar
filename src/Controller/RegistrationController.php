<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        #[Autowire('%mail.from%')] private string $mailFrom,
    ) {
    }

    #[Route('/registreren', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $signature = $this->verifyEmailHelper->generateSignature(
                'app_verify_email',
                (string) $user->getId(),
                (string) $user->getEmail(),
                ['id' => $user->getId()],
            );

            $this->mailer->send((new TemplatedEmail())
                ->from(Address::create($this->mailFrom))
                ->to((string) $user->getEmail())
                ->subject('Bevestig je e-mailadres — AIzichtbaar')
                ->htmlTemplate('registration/confirmation_email.html.twig')
                ->context([
                    'signature' => $signature,
                ])
            );

            $this->addFlash('success', 'Account aangemaakt. Bevestig je e-mailadres via de link in je inbox om in te loggen.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, UserRepository $userRepository): Response
    {
        $user = $userRepository->findOneBy(['id' => $request->query->get('id')]);

        if (!$user) {
            $this->addFlash('error', 'De bevestigingslink is ongeldig.');

            return $this->redirectToRoute('app_register');
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmation($request->getUri(), (string) $user->getId(), (string) $user->getEmail());
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());

            return $this->redirectToRoute('app_register');
        }

        $user->setIsVerified(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'Je e-mailadres is bevestigd. Je kunt nu inloggen.');

        return $this->redirectToRoute('app_login');
    }
}
