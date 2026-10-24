<?php

namespace App\Controller;

use App\Entity\Scan;
use App\Service\MollieService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ScanController extends AbstractController
{
    #[Route('/scan', name: 'app_scan')]
    public function index(): Response
    {
        return $this->render('scan/index.html.twig');
    }

    #[Route('/scan/free', name: 'app_scan_free', methods: ['GET', 'POST'])]
    public function free(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $url = $request->request->get('website');
            if ($url) {
                $scan = new Scan();
                $scan->setWebsiteUrl($url);
                $scan->setType('free');
                $scan->setStatus('completed');
                $scan->setResults([
                    'score' => rand(40, 75),
                    'points' => [
                        'AI-crawlers toegestaan',
                        'Basis content structuur',
                        'Entity consistentie'
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
    public function paid(Request $request, EntityManagerInterface $em, MollieService $mollie, UrlGeneratorInterface $urlGenerator): Response
    {
        if ($request->isMethod('POST')) {
            $url = $request->request->get('website');
            if ($url) {
                $scan = new Scan();
                $scan->setWebsiteUrl($url);
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
    public function result(Scan $scan): Response
    {
        return $this->render('scan/result.html.twig', ['scan' => $scan]);
    }

    #[Route('/mollie/webhook', name: 'app_mollie_webhook', methods: ['POST'])]
    public function webhook(Request $request, EntityManagerInterface $em, MollieService $mollie): Response
    {
        $paymentId = $request->request->get('id');
        if ($paymentId) {
            $payment = $mollie->getPayment($paymentId);
            $scan = $em->getRepository(Scan::class)->findOneBy(['molliePaymentId' => $paymentId]);

            if ($scan && $payment->isPaid()) {
                $scan->setStatus('paid');
                $scan->setResults([
                    'score' => rand(60, 90),
                    'detailed' => true,
                    'recommendations' => [
                        'Voeg answer-first paragrafen toe',
                        'Verbeter brand mentions',
                        'Optimaliseer voor AI-crawlers',
                        'Voeg concrete cijfers en quotes toe'
                    ]
                ]);
                $em->flush();
            }
        }

        return new Response('OK');
    }
}
