<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\PublicSite;
use App\Tests\AppWebTestCase;

class PublicSiteControllerTest extends AppWebTestCase
{
    private const PASSWORD = 'Blauwe-Kaas-Fiets-2026!';

    public function testDirectoryListsOnlyIndexableSites(): void
    {
        $this->createSite('zichtbaar.nl', PublicSite::STATUS_INDEXABLE);
        $this->createSite('verborgen.nl', PublicSite::STATUS_NOINDEX);

        $crawler = $this->client->request('GET', '/websites');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/websites/zichtbaar-nl"]');
        $this->assertCount(0, $crawler->filter('a[href="/websites/verborgen-nl"]'));
    }

    public function testIndexablePageHasCanonicalAndStructuredData(): void
    {
        $this->createSite('zichtbaar.nl', PublicSite::STATUS_INDEXABLE);

        $this->client->request('GET', '/websites/zichtbaar-nl');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('meta[name="robots"][content="index,follow"]');
        $this->assertSelectorExists('link[rel="canonical"][href="http://localhost/websites/zichtbaar-nl"]');
        $this->assertSelectorExists('meta[property="og:title"]');
        $this->assertSelectorExists('meta[property="og:description"]');
        $this->assertSelectorExists('script[type="application/ld+json"]');
    }

    public function testNoindexPageTellsSearchEnginesToStayOut(): void
    {
        $this->createSite('verborgen.nl', PublicSite::STATUS_NOINDEX);

        $this->client->request('GET', '/websites/verborgen-nl');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('meta[name="robots"][content="noindex,follow"]');
    }

    public function testRemovedSiteIsGone(): void
    {
        $this->createSite('weg.nl', PublicSite::STATUS_REMOVED);

        $this->client->request('GET', '/websites/weg-nl');

        $this->assertResponseStatusCodeSame(410);
    }

    public function testSitemapListsOnlyIndexableSites(): void
    {
        $this->createSite('zichtbaar.nl', PublicSite::STATUS_INDEXABLE);
        $this->createSite('verborgen.nl', PublicSite::STATUS_NOINDEX);

        $this->client->request('GET', '/sitemap.xml');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('/websites/zichtbaar-nl', (string) $this->client->getResponse()->getContent());
        $this->assertStringNotContainsString('verborgen-nl', (string) $this->client->getResponse()->getContent());
    }

    public function testRobotsTxtPointsToSitemapAndHidesPrivatePages(): void
    {
        $this->client->request('GET', '/robots.txt');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string) $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Disallow: /scan/result/', (string) $this->client->getResponse()->getContent());
    }

    public function testAdminCanRemoveASite(): void
    {
        $site = $this->createSite('zichtbaar.nl', PublicSite::STATUS_INDEXABLE);
        $admin = $this->createUser('beheer@example.com', self::PASSWORD, true, ['ROLE_ADMIN']);
        $this->client->loginUser($admin);
        // The token lives in the session, which only exists once a request has run. Take it from the rendered form.
        $crawler = $this->client->request('GET', '/admin/websites');
        $token = $crawler->filter('form input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/websites/'.$site->getId().'/status', ['status' => PublicSite::STATUS_REMOVED, '_token' => $token]);

        $this->assertResponseRedirects('/admin/websites');
        $this->entityManager()->clear();
        $this->assertSame(PublicSite::STATUS_REMOVED, $this->entityManager()->find(PublicSite::class, $site->getId())->getStatus());
    }

    private function createSite(string $domain, string $status): PublicSite
    {
        $site = new PublicSite($domain, str_replace('.', '-', $domain));
        $site->setName(ucfirst($domain))
            ->setSummary('Testbedrijf voor de directory.')
            ->setScore(50)
            ->setStatus($status);
        $this->entityManager()->persist($site);
        $this->entityManager()->flush();

        return $site;
    }
}
