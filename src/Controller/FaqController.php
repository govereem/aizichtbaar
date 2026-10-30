<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FaqController extends AbstractController
{
    #[Route('/veelgestelde-vragen', name: 'app_faq')]
    public function index(): Response
    {
        return $this->render('faq/index.html.twig');
    }
}
