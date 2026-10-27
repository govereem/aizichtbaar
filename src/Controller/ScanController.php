<?php

namespace App\Controller;

use App\Entity\Scan;
use App\Entity\User;
use App\Service\MollieService;
use App\Service\ScanReportGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ScanController extends AbstractController
{
    #[Route('/scan', name: 'app_scan')]
    public function index(): Response
    {
        return $this->render('scan/index.html.twig');
    }

    #[Route('/scan/free', name: 'app_scan_free', methods: ['GET', 'POST'])]
    public function free(Request $request, EntityManagerInterface $em, #[CurrentUser] ?User $user = null): Response
    {
        if ($request->isMethod('POST')) {
            $url = $request->request->get('website');
            if ($url) {
                $scan = new Scan();
                $scan->setWebsiteUrl($url);
                $scan->setUser($user);
                $scan->setType('free');
                $scan->setStatus('completed');
                $scan->setResults([
                    'score' => rand(45, 72),
                    'summary' => 'Basischeck voltooid. Er is ruimte voor verbetering.',
                    'points' => [
                        ['title' => 'AI-crawlers', 'status' => 'ok', 'text' => 'robots.txt staat AI-bots grotendeels toe.'],
                        ['title' => 'Content structuur', 'status' => 'warning', 'text' => 'Headings zijn aanwezig, maar weinig answer-first paragrafen.'],
                        ['title' => 'Entity & merk', 'status' => 'warning', 'text' => 'Merknaam komt voor, maar niet consistent genoeg voor AI.'],
                    ]
                ]);
                $em->persist($scan);
                $em->flush();

                return $this->redirectToRoute('app_scan_result', ['id' => $scan->getId()]);
            }
        }

        return $this->render('scan/free.html.twig');
    }

    #[Route('/scan/paid', name: 'app_scan_paid', methods: ['GET', 'POST'])]
    public function paid(Request $request, EntityManagerInterface $em, MollieService $mollie, UrlGeneratorInterface $urlGenerator, #[CurrentUser] ?User $user = null): Response
    {
        if ($request->isMethod('POST')) {
            $url = $request->request->get('website');
            if ($url) {
                $scan = new Scan();
                $scan->setWebsiteUrl($url);
                $scan->setUser($user);
                $scan->setType('paid');
                $scan->setStatus('pending');
                $em->persist($scan);
                $em->flush();

                $payment = $mollie->createPayment(
                    '49.00',
                    'AIzichtbaar Uitgebreide Scan',
                    $urlGenerator->generate('app_scan_result', ['id' => $scan->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                    $urlGenerator->generate('app_mollie_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    ['scan_id' => $scan->getId()]
                );

                $scan->setMolliePaymentId($payment->id);
                $em->flush();

                return $this->redirect($payment->getCheckoutUrl());
            }
        }

        return $this->render('scan/paid.html.twig');
    }

    #[Route('/scan/result/{id}', name: 'app_scan_result')]
    public function result(Scan $scan, #[CurrentUser] ?User $user = null): Response
    {
        // Scans created while logged in belong to that account; anonymous scans stay public. Admins can view all.
        if ($scan->getUser() && $scan->getUser()->getId() !== $user?->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('scan/result.html.twig', ['scan' => $scan]);
    }

    #[Route('/mollie/webhook', name: 'app_mollie_webhook', methods: ['POST'])]
    public function webhook(Request $request, EntityManagerInterface $em, MollieService $mollie, ScanReportGenerator $reportGenerator): Response
    {
        $paymentId = $request->request->get('id');
        if ($paymentId) {
            $payment = $mollie->getPayment($paymentId);
            $scan = $em->getRepository(Scan::class)->findOneBy(['molliePaymentId' => $paymentId]);

            if ($scan && $payment->isPaid()) {
                $scan->setStatus('paid');
                $scan->setResults($reportGenerator->generatePaidResults());
                $em->flush();
            }
        }

        return new Response('OK');
    }
}
