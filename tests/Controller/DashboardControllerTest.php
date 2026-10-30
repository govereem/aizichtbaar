<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Scan;
use App\Entity\User;
use App\Tests\AppWebTestCase;

class DashboardControllerTest extends AppWebTestCase
{
    private const PASSWORD = 'Blauwe-Kaas-Fiets-2026!';

    public function testDashboardShowsAtMostFiveScans(): void
    {
        $owner = $this->createUser('eigenaar@example.com', self::PASSWORD);
        $this->createScans($owner, 7);

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertCount(5, $crawler->filter('ul > li'));
        $this->assertSelectorExists('a[href="/dashboard/scans"]');
    }

    public function testScansPageShowsAllScansInGridByDefault(): void
    {
        $owner = $this->createUser('eigenaar@example.com', self::PASSWORD);
        $this->createScans($owner, 7);

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/dashboard/scans');

        $this->assertResponseIsSuccessful();
        $this->assertCount(7, $crawler->filter('.grid > div'));
        $this->assertCount(0, $crawler->filter('tbody tr'));
    }

    public function testScansPageSwitchesToListView(): void
    {
        $owner = $this->createUser('eigenaar@example.com', self::PASSWORD);
        $this->createScans($owner, 7);

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/dashboard/scans?view=list');

        $this->assertResponseIsSuccessful();
        $this->assertCount(7, $crawler->filter('tbody tr'));
        $this->assertCount(0, $crawler->filter('.grid > div'));
    }

    public function testUnknownViewFallsBackToGrid(): void
    {
        $owner = $this->createUser('eigenaar@example.com', self::PASSWORD);
        $this->createScans($owner, 1);

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/dashboard/scans?view=onzin');

        $this->assertCount(1, $crawler->filter('.grid > div'));
    }

    public function testMenuMarksCurrentPage(): void
    {
        $owner = $this->createUser('eigenaar@example.com', self::PASSWORD);

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/dashboard');
        $this->assertCount(1, $crawler->filter('a[aria-current="page"]'));
        $this->assertSame('Dashboard', trim($crawler->filter('a[aria-current="page"]')->text()));

        $crawler = $this->client->request('GET', '/dashboard/scans');
        $this->assertSame('Mijn scans', trim($crawler->filter('a[aria-current="page"]')->text()));
    }

    public function testScansPageRequiresLogin(): void
    {
        $this->client->request('GET', '/dashboard/scans');

        $this->assertResponseRedirects('/inloggen');
    }

    public function testOwnerCanRescanFinishedScan(): void
    {
        $owner = $this->createUser('eigenaar@example.com', self::PASSWORD);
        $scan = $this->createScan($owner, Scan::STATUS_COMPLETED, 'https://voorbeeld.nl');

        $this->client->loginUser($owner);
        $this->client->request('POST', '/scan/'.$scan->getPublicId().'/rescan', ['_token' => $this->rescanToken($scan)]);

        $this->assertResponseRedirects();
        $newScan = $this->entityManager()->getRepository(Scan::class)->findOneBy(['publicId' => $this->publicIdFromLocation()]);
        $this->assertNotNull($newScan);
        $this->assertNotSame($scan->getId(), $newScan->getId());
        $this->assertSame('https://voorbeeld.nl', $newScan->getWebsiteUrl());
        $this->assertSame('free', $newScan->getType());
        $this->assertSame($owner->getId(), $newScan->getUser()?->getId());
    }

    public function testRescanRejectsOtherUser(): void
    {
        $owner = $this->createUser('eigenaar@example.com', self::PASSWORD);
        $other = $this->createUser('ander@example.com', self::PASSWORD);
        $scan = $this->createScan($owner, Scan::STATUS_COMPLETED);

        $this->client->loginUser($other);
        $this->client->request('POST', '/scan/'.$scan->getPublicId().'/rescan', ['_token' => $this->rescanToken($scan)]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertCount(1, $this->entityManager()->getRepository(Scan::class)->findAll());
    }

    public function testRescanRejectsInvalidToken(): void
    {
        $owner = $this->createUser('eigenaar@example.com', self::PASSWORD);
        $scan = $this->createScan($owner, Scan::STATUS_COMPLETED);

        $this->client->loginUser($owner);
        $this->client->request('POST', '/scan/'.$scan->getPublicId().'/rescan', ['_token' => 'vervalst']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertCount(1, $this->entityManager()->getRepository(Scan::class)->findAll());
    }

    public function testRescanRequiresOwnerForAnonymousScan(): void
    {
        $scan = $this->createScan(null, Scan::STATUS_COMPLETED);

        $this->client->request('POST', '/scan/'.$scan->getPublicId().'/rescan', ['_token' => $this->rescanToken($scan)]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRescanOfRunningScanIsIgnored(): void
    {
        $owner = $this->createUser('eigenaar@example.com', self::PASSWORD);
        $scan = $this->createScan($owner, Scan::STATUS_RUNNING);

        $this->client->loginUser($owner);
        $this->client->request('POST', '/scan/'.$scan->getPublicId().'/rescan', ['_token' => $this->rescanToken($scan)]);

        $this->assertResponseRedirects('/scan/result/'.$scan->getPublicId());
        $this->assertCount(1, $this->entityManager()->getRepository(Scan::class)->findAll());
    }

    private function createScan(?User $user, string $status, string $url = 'https://voorbeeld.nl'): Scan
    {
        $scan = new Scan();
        $scan->setWebsiteUrl($url);
        $scan->setUser($user);
        $scan->setType('free');
        $scan->setStatus($status);
        $this->entityManager()->persist($scan);
        $this->entityManager()->flush();

        return $scan;
    }

    private function createScans(User $user, int $count): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $this->createScan($user, Scan::STATUS_COMPLETED, sprintf('https://site%d.nl', $i));
        }
    }

    private function rescanToken(Scan $scan): string
    {
        return static::getContainer()->get('security.csrf.token_manager')->getToken('rescan-'.$scan->getPublicId())->getValue();
    }

    private function publicIdFromLocation(): string
    {
        $location = (string) $this->client->getResponse()->headers->get('Location');

        return basename((string) parse_url($location, PHP_URL_PATH));
    }
}
