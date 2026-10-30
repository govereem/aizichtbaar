<?php

declare(strict_types=1);

namespace App\Controller;

use App\Kennisbank\KnowledgeBase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class KnowledgeBaseController extends AbstractController
{
    #[Route('/kennisbank', name: 'app_kennisbank')]
    public function index(KnowledgeBase $knowledgeBase): Response
    {
        return $this->render('kennisbank/index.html.twig', ['articles' => $knowledgeBase->all()]);
    }

    #[Route('/kennisbank/{slug}', name: 'app_kennisbank_article')]
    public function article(string $slug, KnowledgeBase $knowledgeBase): Response
    {
        $article = $knowledgeBase->find($slug) ?? throw new NotFoundHttpException();

        return $this->render('kennisbank/article.html.twig', [
            'article' => $article,
            'articles' => $knowledgeBase->all(),
        ]);
    }
}
