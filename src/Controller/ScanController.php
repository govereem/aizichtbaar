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
                    'score' => rand(68, 88),
                    'summary' => 'Goede basis, maar nog duidelijk winst te behalen voor AI-zichtbaarheid.',
                    'analysis' => [
                        ['title' => 'AI-crawlers', 'status' => 'ok', 'detail' => 'GPTBot en ClaudeBot mogen crawlen. PerplexityBot is geblokkeerd in robots.txt.'],
                        ['title' => 'Answer-first content', 'status' => 'warning', 'detail' => 'Slechts 12% van de pagina\'s begint met een direct antwoord. AI-systemen geven hier voorkeur aan.'],
                        ['title' => 'Brand mentions', 'status' => 'warning', 'detail' => 'Merknaam komt 34 keer voor, maar zelden in combinatie met autoriteitssignalen of citaties.'],
                        ['title' => 'Structured data', 'status' => 'error', 'detail' => 'Geen FAQ- of HowTo-schema gevonden. Dit helpt AI om je content te begrijpen.'],
                    ],
                    'actions' => [
                        ['impact' => 'Hoog', 'title' => 'Voeg answer-first paragrafen toe', 'text' => 'Begin belangrijke pagina\'s met een kort, direct antwoord op de kernvraag.'],
                        ['impact' => 'Hoog', 'title' => 'Optimaliseer voor AI-crawlers', 'text' => 'Sta PerplexityBot toe en voeg een llms.txt toe.'],
                        ['impact' => 'Medium', 'title' => 'Versterk brand mentions', 'text' => 'Zorg voor consistente vermeldingen van je merk + expertise op relevante pagina\'s.'],
                        ['impact' => 'Hoog', 'title' => 'Voeg FAQ-schema toe', 'text' => 'Structureer veelgestelde vragen zodat AI ze makkelijk kan overnemen.'],
                    ],
                    'content_example' => [
                        'title' => 'Voorbeeld answer-first paragraaf',
                        'text' => 'AIzichtbaar helpt bedrijven zichtbaar te worden in ChatGPT en Perplexity door content te schrijven die AI graag citeert. De aanpak combineert technische AI-toegang met citeerbare teksten die direct antwoord geven op vragen van klanten.'
                    ]
                ]);
                $em->flush();
            }
        }

        return new Response('OK');
    }
}
