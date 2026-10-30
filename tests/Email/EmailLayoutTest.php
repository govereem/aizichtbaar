<?php

declare(strict_types=1);

namespace App\Tests\Email;

use App\Tests\AppWebTestCase;

class EmailLayoutTest extends AppWebTestCase
{
    public function testRegistrationEmailUsesSharedLayout(): void
    {
        $this->client->request('GET', '/registreren');
        $this->client->submitForm('Account aanmaken', [
            'registration_form[email]' => 'klant@example.com',
            'registration_form[plainPassword][first]' => 'Blauwe-Kaas-Fiets-2026!',
            'registration_form[plainPassword][second]' => 'Blauwe-Kaas-Fiets-2026!',
            'registration_form[agreeTerms]' => true,
        ]);

        $this->assertEmailCount(1);
        $this->assertSharedLayout('E-mailadres bevestigen');
    }

    public function testResetPasswordEmailUsesSharedLayout(): void
    {
        $this->createUser('klant@example.com', 'Oud-Wachtwoord-Lang-2025!');

        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Stuur resetlink', ['reset_password_request_form[email]' => 'klant@example.com']);

        $this->assertEmailCount(1);
        $this->assertSharedLayout('Nieuw wachtwoord instellen');
    }

    private function assertSharedLayout(string $buttonLabel): void
    {
        $html = (string) $this->getMailerMessage()->getHtmlBody();

        $this->assertStringContainsString('/images/logo-email.png', $html, 'Logo missing from header.');
        $this->assertStringContainsString('alt="AIzichtbaar"', $html);
        $this->assertStringContainsString($buttonLabel, $html, 'Call-to-action button missing.');
        $this->assertStringContainsString('Maak je zichtbaar in AI.', $html, 'Footer tagline missing.');
    }
}
