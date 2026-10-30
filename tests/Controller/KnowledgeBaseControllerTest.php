<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Kennisbank\KnowledgeBase;
use App\Tests\AppWebTestCase;

class KnowledgeBaseControllerTest extends AppWebTestCase
{
    public function testOverviewListsAllArticles(): void
    {
        $this->client->request('GET', '/kennisbank');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Kennisbank');
        $this->assertCount(
            count(static::getContainer()->get(KnowledgeBase::class)->all()),
            $this->client->getCrawler()->filter('main a[href^="/kennisbank/"]'),
        );
    }

    public function testEveryArticleHasOneH1CanonicalArticleDataAndScanCta(): void
    {
        foreach (static::getContainer()->get(KnowledgeBase::class)->all() as $article) {
            $this->client->request('GET', '/kennisbank/'.$article->getSlug());

            $this->assertResponseIsSuccessful();
            $this->assertSelectorCount(1, 'h1');
            $this->assertSelectorTextContains('h1', $article->getTitle());
            $this->assertSelectorExists('link[rel="canonical"][href="http://localhost/kennisbank/'.$article->getSlug().'"]');
            $this->assertSelectorExists('a[href="/products"]');
            $this->assertStringContainsString('"@type":"Article"', (string) $this->client->getResponse()->getContent());
        }
    }

    public function testUnknownArticleIsNotFound(): void
    {
        $this->client->request('GET', '/kennisbank/bestaat-niet');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testFooterLinksToKennisbank(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('footer a[href="/kennisbank"]');
    }
}
