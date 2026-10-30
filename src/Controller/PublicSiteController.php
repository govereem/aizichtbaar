<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PublicSite;
use App\Repository\PublicSiteRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PublicSiteController extends AbstractController
{
    private const PER_PAGE = 24;

    #[Route('/websites', name: 'app_public_sites')]
    public function index(Request $request, PublicSiteRepository $sites): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $paginator = $sites->findIndexablePage($page, self::PER_PAGE);

        return $this->render('public_site/index.html.twig', [
            'sites' => $paginator,
            'page' => $page,
            'pages' => (int) ceil(count($paginator) / self::PER_PAGE),
        ]);
    }

    #[Route('/websites/{slug}', name: 'app_public_site_show')]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] PublicSite $site): Response
    {
        // Removed sites are gone for good: 410 tells search engines to drop them, unlike a 404 which they retry.
        if (PublicSite::STATUS_REMOVED === $site->getStatus()) {
            throw new GoneHttpException();
        }

        return $this->render('public_site/show.html.twig', ['site' => $site]);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap')]
    public function sitemap(PublicSiteRepository $sites): Response
    {
        $response = $this->render('sitemap.xml.twig', [
            'sites' => $sites->findBy(['status' => PublicSite::STATUS_INDEXABLE], ['domain' => 'ASC']),
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    #[Route('/robots.txt', name: 'app_robots')]
    public function robots(): Response
    {
        // Private pages stay out of search: the admin and account areas, and scan results, which are only shared by their link.
        $body = <<<TXT
        User-agent: *
        Allow: /
        Disallow: /admin
        Disallow: /dashboard
        Disallow: /scan/result/

        Sitemap: {$this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL)}

        TXT;

        return new Response($body, headers: ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
