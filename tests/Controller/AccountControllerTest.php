<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;

class AccountControllerTest extends AppWebTestCase
{
    private const PASSWORD = 'Blauwe-Kaas-Fiets-2026!';

    public function testRegisteredUserMustVerifyEmailBeforeLoggingIn(): void
    {
        $this->client->request('GET', '/registreren');
        $this->client->submitForm('Account aanmaken', [
            'registration_form[email]' => 'klant@example.com',
            'registration_form[plainPassword][first]' => self::PASSWORD,
            'registration_form[plainPassword][second]' => self::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]);

        $this->assertResponseRedirects('/inloggen');
        $this->assertEmailCount(1);
        $verifyUrl = $this->urlFromLastEmail('#https?://[^"\s<]+/verify/email[^"\s<]*#');

        $this->client->request('GET', '/inloggen');
        $this->client->submitForm('Inloggen', ['_username' => 'klant@example.com', '_password' => self::PASSWORD]);
        $this->assertResponseRedirects('/inloggen');
        $this->client->followRedirect();
        $this->assertStringContainsString('Bevestig eerst je e-mailadres', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', $verifyUrl);
        $this->assertResponseRedirects('/inloggen');
        $this->assertTrue($this->userByEmail('klant@example.com')->isVerified());

        $this->client->request('GET', '/inloggen');
        $this->client->submitForm('Inloggen', ['_username' => 'klant@example.com', '_password' => self::PASSWORD]);
        $this->assertResponseRedirects('/dashboard');
    }

    public function testDashboardRequiresLogin(): void
    {
        $this->client->request('GET', '/dashboard');

        $this->assertResponseRedirects('/inloggen');
    }

    public function testVerifiedUserCanLoginAndSeeDashboard(): void
    {
        $this->createUser('klant@example.com', self::PASSWORD);

        $this->client->request('GET', '/inloggen');
        $this->client->submitForm('Inloggen', ['_username' => 'klant@example.com', '_password' => self::PASSWORD]);
        $this->assertResponseRedirects('/dashboard');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Dashboard');
    }
}
