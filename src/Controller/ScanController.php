<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Scan;
use App\Entity\User;
use App\Message\RunScanMessage;
use App\Service\MollieService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ScanController extends AbstractController
{
    private const int MAX_COMPETITORS = 3;

    #[Route('/scan/free', name: 'app_scan_free', methods: ['GET', 'POST'])]
    public function free(Request $request, EntityManagerInterface $em, MessageBusInterface $bus, ValidatorInterface $validator, #[Autowire(service: 'limiter.anonymous_free_scan')] RateLimiterFactory $anonymousLimiter, #[CurrentUser] ?User $user = null): Response
    {
        if ($request->isMethod('POST')) {
            $url = trim((string) $request->request->get('website'));

            $violations = $validator->validate($url, $this->websiteConstraints());
            if (count($violations) > 0) {
                $this->addFlash('error', $violations[0]->getMessage());

                return $this->redirectToRoute($this->scanFormRoute($request, 'app_scan_free'));
            }

            // Only anonymous visitors are limited. Their scans are the ones that can be published, so they are capped per IP.
            if (null === $user) {
                $limit = $anonymousLimiter->create((string) $request->getClientIp())->consume();
                if (!$limit->isAccepted()) {
                    return $this->render('scan/free.html.twig', [
                        'limit_reached' => true,
                        'retry_after' => $limit->getRetryAfter(),
                    ], new Response(status: Response::HTTP_TOO_MANY_REQUESTS));
                }
            }

            $scan = new Scan();
            $scan->setWebsiteUrl($url);
            $scan->setUser($user);
            $scan->setType('free');
            $scan->setStatus(Scan::STATUS_QUEUED);
            $em->persist($scan);
            // Flush before dispatching, so the handler can find the scan.
            $em->flush();

            $bus->dispatch(new RunScanMessage($scan->getId()));

            return $this->redirectToRoute('app_scan_result', ['publicId' => $scan->getPublicId()]);
        }

        return $this->render('scan/free.html.twig');
    }

    #[Route('/scan/paid', name: 'app_scan_paid', methods: ['GET', 'POST'])]
    public function paid(Request $request, EntityManagerInterface $em, MollieService $mollie, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator, #[CurrentUser] ?User $user = null): Response
    {
        if (!$this->getParameter('scan.paid_enabled')) {
            return $this->redirectToRoute('app_products');
        }

        if ($request->isMethod('POST')) {
            $url = trim((string) $request->request->get('website'));
            $competitors = $this->competitorUrls((string) $request->request->get('competitors'));
            $email = $user?->getEmail() ?? trim((string) $request->request->get('email'));

            $violations = [
                ...$validator->validate($url, $this->websiteConstraints()),
                ...$validator->validate($email, $this->emailConstraints()),
            ];
            foreach ($competitors as $competitor) {
                $violations = [...$violations, ...$validator->validate($competitor, $this->websiteConstraints())];
            }
            if ([] !== $violations) {
                $this->addFlash('error', $violations[0]->getMessage());

                return $this->redirectToRoute($this->scanFormRoute($request, 'app_scan_paid'));
            }

            $scan = new Scan();
            $scan->setWebsiteUrl($url);
            $scan->setUser($user);
            $scan->setEmail($email);
            $scan->setCompetitorUrls($competitors);
            $scan->setType('paid');
            $scan->setStatus(Scan::STATUS_PENDING);
            $em->persist($scan);
            $em->flush();

            $payment = $mollie->createPayment(
                $this->getParameter('scan.paid_price'),
                'AIzichtbaar Uitgebreide Scan',
                $urlGenerator->generate('app_scan_result', ['publicId' => $scan->getPublicId()], UrlGeneratorInterface::ABSOLUTE_URL),
                $urlGenerator->generate('app_mollie_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL),
                ['scan_id' => $scan->getId()]
            );

            $scan->setMolliePaymentId($payment->id);
            $em->flush();

            return $this->redirect($payment->getCheckoutUrl());
        }

        return $this->render('scan/paid.html.twig');
    }

    #[Route('/scan/result/{publicId}', name: 'app_scan_result')]
    public function result(#[MapEntity(mapping: ['publicId' => 'publicId'])] Scan $scan, #[CurrentUser] ?User $user = null): Response
    {
        // Scans created while logged in belong to that account; anonymous scans stay reachable by their unguessable link. Admins can view all.
        if ($scan->getUser() && $scan->getUser()->getId() !== $user?->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('scan/result.html.twig', ['scan' => $scan]);
    }

    #[Route('/scan/{publicId}/rescan', name: 'app_scan_rescan', methods: ['POST'])]
    public function rescan(#[MapEntity(mapping: ['publicId' => 'publicId'])] Scan $scan, Request $request, EntityManagerInterface $em, MessageBusInterface $bus, #[CurrentUser] ?User $user = null): Response
    {
        if (!$user || !$scan->getUser() || $scan->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('rescan-'.$scan->getPublicId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ongeldige beveiligingstoken.');
        }

        // A scan that is still running would be duplicated, so only finished scans can be run again.
        if (!in_array($scan->getStatus(), [Scan::STATUS_COMPLETED, Scan::STATUS_FAILED], true)) {
            return $this->redirectToRoute('app_scan_result', ['publicId' => $scan->getPublicId()]);
        }

        // Same type and URL, but a new row, so the earlier result stays intact. Paid scans are not charged again.
        $rescan = new Scan();
        $rescan->setWebsiteUrl($scan->getWebsiteUrl());
        $rescan->setUser($user);
        $rescan->setEmail($scan->getEmail());
        $rescan->setCompetitorUrls($scan->getCompetitorUrls());
        $rescan->setType($scan->getType());
        $rescan->setStatus(Scan::STATUS_QUEUED);
        $em->persist($rescan);
        // Flush before dispatching, so the handler can find the scan.
        $em->flush();

        $bus->dispatch(new RunScanMessage($rescan->getId()));

        return $this->redirectToRoute('app_scan_result', ['publicId' => $rescan->getPublicId()]);
    }

    #[Route('/mollie/webhook', name: 'app_mollie_webhook', methods: ['POST'])]
    public function webhook(Request $request, EntityManagerInterface $em, MollieService $mollie, MessageBusInterface $bus): Response
    {
        $paymentId = $request->request->get('id');
        if ($paymentId) {
            $payment = $mollie->getPayment($paymentId);
            $scan = $em->getRepository(Scan::class)->findOneBy(['molliePaymentId' => $paymentId]);

            // Only a pending scan starts. Mollie redelivers webhooks, and a second delivery must not run the scan again.
            if ($scan && Scan::STATUS_PENDING === $scan->getStatus() && $payment->isPaid()) {
                $scan->setStatus(Scan::STATUS_PAID);
                $em->flush();

                $bus->dispatch(new RunScanMessage($scan->getId()));
            }
        }

        return new Response('OK');
    }

    /**
     * Forms in the dashboard post context=dashboard, so validation errors stay in the side menu layout.
     */
    private function scanFormRoute(Request $request, string $publicRoute): string
    {
        return 'dashboard' === $request->request->get('context') ? 'app_dashboard_scan_new' : $publicRoute;
    }

    /**
     * One competitor URL per line, at most three. Blank lines are ignored.
     *
     * @return list<string>
     */
    private function competitorUrls(string $input): array
    {
        $lines = array_filter(array_map('trim', preg_split('/\R/', $input) ?: []));

        return array_values(array_slice($lines, 0, self::MAX_COMPETITORS));
    }

    /**
     * @return list<Assert\Constraint>
     */
    private function websiteConstraints(): array
    {
        return [
            new Assert\NotBlank(message: 'Vul de URL van je website in.'),
            new Assert\Url(message: 'Vul een geldige website-URL in, zoals https://jouwwebsite.nl.', requireTld: true),
        ];
    }

    /**
     * @return list<Assert\Constraint>
     */
    private function emailConstraints(): array
    {
        return [
            new Assert\NotBlank(message: 'Vul je e-mailadres in, dan sturen we het rapport daarheen.'),
            new Assert\Email(message: 'Vul een geldig e-mailadres in.'),
        ];
    }
}
