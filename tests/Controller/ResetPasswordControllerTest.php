<?php

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;

class ResetPasswordControllerTest extends AppWebTestCase
{
    public function testUserCanSetNewPasswordViaEmailLink(): void
    {
        $this->createUser('klant@example.com', 'Oud-Wachtwoord-Lang-2025!');

        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Stuur resetlink', ['reset_password_request_form[email]' => 'klant@example.com']);
        $this->assertResponseRedirects('/reset-password/check-email');
        $this->assertEmailCount(1);

        $resetUrl = $this->urlFromLastEmail('#https?://[^"\s<]+/reset-password/reset/[^"\s<]+#');
        $this->client->request('GET', $resetUrl);
        $this->assertResponseRedirects('/reset-password/reset');
        $this->client->followRedirect();

        $newPassword = 'Nieuw-Wachtwoord-Fiets-2026!';
        $this->client->submitForm('Wachtwoord opslaan', [
            'change_password_form[plainPassword][first]' => $newPassword,
            'change_password_form[plainPassword][second]' => $newPassword,
        ]);
        $this->assertResponseRedirects('/inloggen');

        $this->client->request('GET', '/inloggen');
        $this->client->submitForm('Inloggen', ['_username' => 'klant@example.com', '_password' => $newPassword]);
        $this->assertResponseRedirects('/dashboard');
    }

    public function testUnknownEmailDoesNotSendMail(): void
    {
        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Stuur resetlink', ['reset_password_request_form[email]' => 'onbekend@example.com']);

        $this->assertResponseRedirects('/reset-password/check-email');
        $this->assertEmailCount(0);
    }
}
