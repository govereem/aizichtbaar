<?php

namespace App\Tests\Controller;

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

    public function testAnonymousScanStaysPublic(): void
    {
        $this->client->request('POST', '/scan/free', ['website' => 'https://voorbeeld.nl']);
        $this->assertResponseRedirects();
        $resultUrl = (string) $this->client->getResponse()->headers->get('Location');

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', $resultUrl);
        $this->assertResponseIsSuccessful();
    }
}
