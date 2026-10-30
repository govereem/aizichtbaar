<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;

class HomeControllerTest extends AppWebTestCase
{
    public function testHomePageHasOneH1AndAnswerFirstSections(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(1, 'h1');
        $this->assertSelectorTextContains('h2', 'Wat doet AIzichtbaar?');
        $this->assertCount(2, $this->client->getCrawler()->filter('h2'));
    }

    public function testHomePageHasOrganizationAndWebsiteJsonLdWithoutSameAs(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        $types = [];
        $this->client->getCrawler()->filter('script[type="application/ld+json"]')->each(function ($node) use (&$types): void {
            $data = json_decode($node->text(), true, 512, JSON_THROW_ON_ERROR);
            $types[$data['@type']] = $data;
        });

        $this->assertArrayHasKey('Organization', $types);
        $this->assertArrayHasKey('WebSite', $types);
        $this->assertSame('AIzichtbaar', $types['Organization']['name']);
        $this->assertSame('82005222', $types['Organization']['identifier']['value']);
        $this->assertArrayNotHasKey('sameAs', $types['Organization']);
    }

    public function testHomePageHasDescriptionAndCanonical(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('meta[name="description"][content*="ChatGPT"]');
        $this->assertSelectorExists('link[rel="canonical"][href="http://localhost/"]');
        $this->assertSelectorExists('meta[property="og:site_name"][content="AIzichtbaar"]');
    }
}
