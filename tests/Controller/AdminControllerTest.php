<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Scan;
use App\Tests\AppWebTestCase;

class AdminControllerTest extends AppWebTestCase
{
    public function testNonAdminCannotOpenAdminArea(): void
    {
        $user = $this->createUser('gebruiker@example.com', 'Blauwe-Kaas-Fiets-2026!');

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/users');
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/scans/new');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanCreateFreeUitgebreideScanForUser(): void
    {
        $admin = $this->createUser('admin@example.com', 'Blauwe-Kaas-Fiets-2026!', roles: ['ROLE_ADMIN']);
        $owner = $this->createUser('klant@example.com', 'Blauwe-Kaas-Fiets-2026!');

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/scans/new');
        $this->assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Scan aanmaken')->form([
            'admin_paid_scan[user]' => $owner->getId(),
            'admin_paid_scan[websiteUrl]' => 'https://voorbeeld.nl',
        ]));
        $this->assertResponseRedirects();

        $scan = $this->entityManager()->getRepository(Scan::class)->findOneBy(['user' => $owner]);
        $this->assertSame('paid', $scan->getType());
        // The scan is dispatched and runs inline in the test env, so it ends up completed.
        $this->assertSame('completed', $scan->getStatus());
        $this->assertNull($scan->getMolliePaymentId());
        $this->assertArrayHasKey('actions', $scan->getResults());

        $this->client->loginUser($owner);
        $this->client->request('GET', '/dashboard');
        $this->assertSelectorTextContains('body', 'Uitgebreide scan');
    }

    public function testAdminCanViewScanOfAnotherUser(): void
    {
        $admin = $this->createUser('admin@example.com', 'Blauwe-Kaas-Fiets-2026!', roles: ['ROLE_ADMIN']);
        $owner = $this->createUser('klant@example.com', 'Blauwe-Kaas-Fiets-2026!');

        $this->client->loginUser($owner);
        $this->client->request('POST', '/scan/free', ['website' => 'https://voorbeeld.nl']);
        $resultUrl = (string) $this->client->getResponse()->headers->get('Location');

        $this->client->loginUser($admin);
        $this->client->request('GET', $resultUrl);
        $this->assertResponseIsSuccessful();
    }

    public function testAdminCanSwitchToUnverifiedUserAndExit(): void
    {
        $admin = $this->createUser('admin@example.com', 'Blauwe-Kaas-Fiets-2026!', roles: ['ROLE_ADMIN']);
        $unverified = $this->createUser('nog@example.com', 'Blauwe-Kaas-Fiets-2026!', verified: false);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/dashboard?_switch_user=nog@example.com');
        $this->assertResponseRedirects('/dashboard');

        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Je bekijkt AIzichtbaar als nog@example.com');

        $this->client->request('GET', '/dashboard?_switch_user=_exit');
        $this->client->request('GET', '/admin/users');
        $this->assertResponseIsSuccessful();
    }

    public function testNonAdminCannotSwitchUser(): void
    {
        $user = $this->createUser('gebruiker@example.com', 'Blauwe-Kaas-Fiets-2026!');
        $other = $this->createUser('ander@example.com', 'Blauwe-Kaas-Fiets-2026!');

        $this->client->loginUser($user);
        $this->client->request('GET', '/dashboard?_switch_user=' . $other->getEmail());
        $this->assertResponseStatusCodeSame(403);
    }
}
