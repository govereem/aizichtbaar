<?php

namespace App\Controller;

use App\Entity\Scan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(#[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $scans = $em->getRepository(Scan::class)->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('dashboard/index.html.twig', [
            'scans' => $scans,
        ]);
    }
}
