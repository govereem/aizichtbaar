<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Scan;
use App\Form\AdminPaidScanType;
use App\Message\RunScanMessage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
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
    public function newScan(Request $request, EntityManagerInterface $em, MessageBusInterface $bus): Response
    {
        // Granted by an administrator, so no Mollie payment is needed.
        $scan = new Scan();
        $scan->setType('paid');
        $scan->setStatus(Scan::STATUS_PAID);

        $form = $this->createForm(AdminPaidScanType::class, $scan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($scan);
            $em->flush();

            $bus->dispatch(new RunScanMessage($scan->getId()));

            $this->addFlash('success', sprintf('Uitgebreide scan aangemaakt voor %s.', $scan->getUser()->getEmail()));

            return $this->redirectToRoute('app_scan_result', ['publicId' => $scan->getPublicId()]);
        }

        return $this->render('admin/scan_new.html.twig', [
            'form' => $form,
        ]);
    }
}
