<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Scan;
use App\Tests\AppWebTestCase;

class ScanControllerTest extends AppWebTestCase
{
    public function testScanOfLoggedInUserIsOnlyVisibleToItsOwner(): void
    {
        $owner = $this->createUser('eigenaar@example.com', 'Blauwe-Kaas-Fiets-2026!');
        $other = $this->createUser('ander@example.com', 'Blauwe-Kaas-Fiets-2026!');

        $this->client->loginUser($owner);
        $this->client->request('POST', '/scan/free', ['website' => 'https://voorbeeld.nl']);
        $this->assertResponseRedirects();
        $resultUrl = (string) $this->client->getResponse()->headers->get('Location');

        $this->client->request('GET', $resultUrl);
        $this->assertResponseIsSuccessful();

        $this->client->loginUser($other);
        $this->client->request('GET', $resultUrl);
        $this->assertResponseStatusCodeSame(403);

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', $resultUrl);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testFreeResultShowsActionPlanAndReadyToPasteFiles(): void
    {
        $scan = $this->completedScan('free', [
            'actions' => [['id' => 'llms_txt', 'title' => 'llms.txt ontbreekt', 'text' => 'Plaats een llms.txt.', 'impact' => 'Medium']],
            'content' => ['llms_txt' => '# Voorbeeld', 'organization_json_ld' => '{"@type": "Organization"}', 'robots_snippet' => 'User-agent: GPTBot'],
        ]);

        $crawler = $this->client->request('GET', '/scan/result/'.$scan->getPublicId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h2:contains("Actieplan")');
        $this->assertSelectorExists('h2:contains("Kant-en-klare bestanden")');
        $this->assertStringContainsString('# Voorbeeld', (string) $this->client->getResponse()->getContent());
        $this->assertStringContainsString('User-agent: GPTBot', (string) $this->client->getResponse()->getContent());
        $this->assertStringNotContainsString('Zo los je het op', $crawler->filter('body')->text());
    }

    public function testFailedChecksLinkToTheirReadyToPasteFile(): void
    {
        $scan = $this->completedScan('free', [
            'checks' => [
                ['id' => 'llms_txt', 'title' => 'llms.txt', 'status' => 'warning', 'detail' => 'Geen llms.txt gevonden.', 'weight' => 1],
                ['id' => 'entity', 'title' => 'Entiteit & merk', 'status' => 'error', 'detail' => 'Geen Organization-schema gevonden.', 'weight' => 2],
                ['id' => 'ai_crawlers', 'title' => 'AI-crawlers', 'status' => 'ok', 'detail' => 'Alle crawlers mogen.', 'weight' => 3],
            ],
            'content' => ['llms_txt' => '# Voorbeeld', 'organization_json_ld' => '{}', 'robots_snippet' => ''],
        ]);

        $this->client->request('GET', '/scan/result/'.$scan->getPublicId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="#file-llms"]');
        $this->assertSelectorExists('a[href="#file-jsonld"]');
        $this->assertSelectorNotExists('a[href="#file-robots"]');
        $this->assertSelectorExists('#file-llms');
        $this->assertSelectorExists('#file-jsonld');
    }

    public function testPaidResultShowsStepsForEachAction(): void
    {
        $scan = $this->completedScan('paid', [
            'actions' => [['id' => 'llms_txt', 'title' => 'llms.txt ontbreekt', 'text' => 'Plaats een llms.txt.', 'impact' => 'Hoog', 'why' => 'AI leest hem eerst.', 'steps' => ['Maak het bestand.'], 'effort' => 'laag']],
            'content' => ['llms_txt' => '# Voorbeeld', 'organization_json_ld' => '{}', 'robots_snippet' => ''],
        ]);

        $this->client->request('GET', '/scan/result/'.$scan->getPublicId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Zo los je het op (inspanning: laag)');
        $this->assertSelectorTextContains('body', 'Maak het bestand.');
    }

    public function testAnonymousVisitorGetsThreeFreeScansADay(): void
    {
        // The test cache is cleared after every request, so the first three scans are counted on the limiter directly.
        $this->client->disableReboot();
        $limiter = static::getContainer()->get('limiter.anonymous_free_scan');
        for ($i = 0; $i < 3; ++$i) {
            $this->assertTrue($limiter->create('127.0.0.1')->consume()->isAccepted());
        }

        $this->client->request('POST', '/scan/free', ['website' => 'https://voorbeeld.nl']);

        $this->assertResponseStatusCodeSame(429);
        $this->assertSelectorExists('a[href="/registreren"]');
    }

    public function testLoggedInUserIsNotLimited(): void
    {
        $user = $this->createUser('lid@example.com', 'Blauwe-Kaas-Fiets-2026!');
        $this->client->loginUser($user);

        for ($i = 0; $i < 4; ++$i) {
            $this->client->request('POST', '/scan/free', ['website' => 'https://voorbeeld.nl']);
            $this->assertResponseRedirects();
        }
    }

    public function testAnonymousScanStaysPublic(): void
    {
        $this->client->request('POST', '/scan/free', ['website' => 'https://voorbeeld.nl']);
        $this->assertResponseRedirects();
        $resultUrl = (string) $this->client->getResponse()->headers->get('Location');

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', $resultUrl);
        $this->assertResponseIsSuccessful();
    }

    /**
     * @param array<string, mixed> $results
     */
    private function completedScan(string $type, array $results): Scan
    {
        $scan = new Scan();
        $scan->setWebsiteUrl('https://voorbeeld.nl');
        $scan->setType($type);
        $scan->setStatus(Scan::STATUS_COMPLETED);
        $scan->setResults(['score' => 40, 'summary' => 'Test'] + $results);
        $this->entityManager()->persist($scan);
        $this->entityManager()->flush();

        return $scan;
    }
}
