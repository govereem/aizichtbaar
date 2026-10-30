<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Scan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class DashboardController extends AbstractController
{
    private const RECENT_LIMIT = 5;

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(#[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $scans = $em->getRepository(Scan::class)->findBy(['user' => $user], ['createdAt' => 'DESC'], self::RECENT_LIMIT);

        return $this->render('dashboard/index.html.twig', [
            'scans' => $scans,
        ]);
    }

    #[Route('/dashboard/scans', name: 'app_dashboard_scans')]
    public function scans(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $scans = $em->getRepository(Scan::class)->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $view = 'list' === $request->query->get('view') ? 'list' : 'grid';

        return $this->render('dashboard/scans.html.twig', [
            'scans' => $scans,
            'view' => $view,
        ]);
    }
}
