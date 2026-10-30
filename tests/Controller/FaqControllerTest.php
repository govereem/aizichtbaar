<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;

class FaqControllerTest extends AppWebTestCase
{
    public function testFaqPageIsPublicWithOneH1AndCanonical(): void
    {
        $this->client->request('GET', '/veelgestelde-vragen');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(1, 'h1');
        $this->assertSelectorTextContains('h1', 'Veelgestelde vragen');
        $this->assertSelectorExists('link[rel="canonical"][href="http://localhost/veelgestelde-vragen"]');
    }

    public function testFaqQuestionsAreCollapsibleAnswers(): void
    {
        $this->client->request('GET', '/veelgestelde-vragen');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $this->client->getCrawler()->filter('details > summary')->count());
        $this->assertSelectorTextContains('details summary', 'Wat is AIzichtbaar?');
    }

    public function testFaqPageHasFaqPageJsonLdMatchingTheVisibleQuestions(): void
    {
        $this->client->request('GET', '/veelgestelde-vragen');

        $this->assertResponseIsSuccessful();

        $faqPage = null;
        $this->client->getCrawler()->filter('script[type="application/ld+json"]')->each(function ($node) use (&$faqPage): void {
            $data = json_decode($node->text(), true, 512, JSON_THROW_ON_ERROR);
            if ('FAQPage' === ($data['@type'] ?? null)) {
                $faqPage = $data;
            }
        });

        $this->assertIsArray($faqPage);
        $this->assertCount($this->client->getCrawler()->filter('details')->count(), $faqPage['mainEntity']);
        $this->assertSame('Question', $faqPage['mainEntity'][0]['@type']);
        $this->assertNotEmpty($faqPage['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function testFooterLinksToFaq(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('footer a[href="/veelgestelde-vragen"]');
    }
}
