<?php

declare(strict_types=1);

namespace App\Tests\Kennisbank;

use App\Kennisbank\KnowledgeBase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KnowledgeBaseTest extends KernelTestCase
{
    public function testItHasFiveArticlesWithUniqueSlugs(): void
    {
        $articles = static::getContainer()->get(KnowledgeBase::class)->all();

        $this->assertCount(5, $articles);
        $this->assertCount(5, array_unique(array_map(static fn ($article): string => $article->getSlug(), $articles)));
    }

    public function testEveryArticleHasItsOwnTemplate(): void
    {
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');

        foreach (static::getContainer()->get(KnowledgeBase::class)->all() as $article) {
            $this->assertFileExists($projectDir.'/templates/kennisbank/articles/'.$article->getSlug().'.html.twig');
        }
    }

    public function testFindReturnsNullForUnknownSlug(): void
    {
        $this->assertNull(static::getContainer()->get(KnowledgeBase::class)->find('bestaat-niet'));
    }
}
