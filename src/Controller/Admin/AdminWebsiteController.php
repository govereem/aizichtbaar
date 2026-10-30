<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PublicSite;
use App\Repository\PublicSiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/websites')]
#[IsGranted('ROLE_ADMIN')]
class AdminWebsiteController extends AbstractController
{
    private const STATUSES = [PublicSite::STATUS_INDEXABLE, PublicSite::STATUS_NOINDEX, PublicSite::STATUS_REMOVED];

    #[Route('', name: 'app_admin_websites')]
    public function index(PublicSiteRepository $sites): Response
    {
        return $this->render('admin/websites.html.twig', [
            'sites' => $sites->findBy([], ['domain' => 'ASC']),
        ]);
    }

    #[Route('/{id}/status', name: 'app_admin_website_status', methods: ['POST'])]
    public function status(#[MapEntity] PublicSite $site, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('website-status-'.$site->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ongeldige beveiligingstoken.');
        }

        $status = (string) $request->request->get('status');
        if (!in_array($status, self::STATUSES, true)) {
            throw new BadRequestHttpException('Onbekende status.');
        }

        $site->setStatus($status);
        $em->flush();

        $this->addFlash('success', sprintf('%s is nu %s.', $site->getDomain(), $status));

        return $this->redirectToRoute('app_admin_websites');
    }
}
