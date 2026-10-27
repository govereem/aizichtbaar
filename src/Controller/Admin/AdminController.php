<?php

namespace App\Controller\Admin;

use App\Entity\Scan;
use App\Form\AdminPaidScanType;
use App\Repository\UserRepository;
use App\Service\ScanReportGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/users', name: 'app_admin_users')]
    public function users(UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $users->findBy([], ['email' => 'ASC']),
        ]);
    }

    #[Route('/scans/new', name: 'app_admin_scan_new', methods: ['GET', 'POST'])]
    public function newScan(Request $request, EntityManagerInterface $em, ScanReportGenerator $reportGenerator): Response
    {
        // Granted by an administrator, so no Mollie payment is needed.
        $scan = new Scan();
        $scan->setType('paid');
        $scan->setStatus('paid');

        $form = $this->createForm(AdminPaidScanType::class, $scan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $scan->setResults($reportGenerator->generatePaidResults());
            $em->persist($scan);
            $em->flush();

            $this->addFlash('success', sprintf('Uitgebreide scan aangemaakt voor %s.', $scan->getUser()->getEmail()));

            return $this->redirectToRoute('app_scan_result', ['id' => $scan->getId()]);
        }

        return $this->render('admin/scan_new.html.twig', [
            'form' => $form,
        ]);
    }
}
