<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contact\ContactMailer;
use App\Form\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(Request $request, ContactMailer $mailer, #[Autowire(service: 'limiter.contact_form')] RateLimiterFactory $contactLimiter): Response
    {
        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->render('contact/index.html.twig', ['form' => $form]);
        }

        // Every submission counts, valid or not, so spam bots are limited too.
        if (!$contactLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            return $this->render('contact/index.html.twig', ['form' => $form, 'limit_reached' => true], new Response(status: Response::HTTP_TOO_MANY_REQUESTS));
        }

        if (!$form->isValid()) {
            return $this->render('contact/index.html.twig', ['form' => $form], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $data = $form->getData();
        if (!$mailer->send($data['name'], $data['email'], $data['message'])) {
            $this->addFlash('error', 'Je bericht kon niet worden verstuurd. Probeer het later opnieuw.');

            return $this->redirectToRoute('app_contact');
        }

        $this->addFlash('success', 'Bedankt, je bericht is verstuurd.');

        return $this->redirectToRoute('app_contact');
    }
}
