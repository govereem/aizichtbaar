<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;
use Symfony\Component\Mime\Email;

class ContactControllerTest extends AppWebTestCase
{
    public function testContactPageShowsCompanyDetailsAndForm(): void
    {
        $this->client->request('GET', '/contact');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Contact opnemen');
        $this->assertSelectorTextContains('body', '82005222');
        $this->assertSelectorTextContains('body', 'Gerben Colmjonwei 109');
        $this->assertSelectorTextContains('body', '8915 GJ Leeuwarden');
        $this->assertSelectorExists('form[name="contact_form"]');
    }

    public function testValidMessageIsMailedToSupportWithVisitorAsReplyTo(): void
    {
        $this->client->request('GET', '/contact');
        $this->client->submitForm('Verzenden', [
            'contact_form[name]' => 'Jan Jansen',
            'contact_form[email]' => 'jan@example.com',
            'contact_form[message]' => 'Kunnen jullie mij helpen met mijn scan?',
        ]);

        $this->assertResponseRedirects('/contact');
        $this->assertEmailCount(1);

        $message = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $message);
        $this->assertSame('support@aizichtbaar.nl', $message->getTo()[0]->getAddress());
        $this->assertSame('jan@example.com', $message->getReplyTo()[0]->getAddress());
        $this->assertSame('Nieuw bericht via het contactformulier', $message->getSubject());
        $this->assertStringContainsString('Kunnen jullie mij helpen met mijn scan?', (string) $message->getHtmlBody());

        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Bedankt, je bericht is verstuurd.');
    }

    public function testVisitorNameIsNotPutInTheSubject(): void
    {
        $this->client->request('GET', '/contact');
        $this->client->submitForm('Verzenden', [
            'contact_form[name]' => 'Jan Jansen',
            'contact_form[email]' => 'jan@example.com',
            'contact_form[message]' => 'Een korte vraag over de scan.',
        ]);

        $message = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $message);
        $this->assertStringNotContainsString('Jan Jansen', (string) $message->getSubject());
    }

    public function testInvalidMessageIsRejectedWithoutMail(): void
    {
        $this->client->request('GET', '/contact');
        $this->client->submitForm('Verzenden', [
            'contact_form[name]' => '',
            'contact_form[email]' => 'geen-adres',
            'contact_form[message]' => 'kort',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertEmailCount(0);
        $this->assertSelectorTextContains('body', 'Vul je naam in.');
        $this->assertSelectorTextContains('body', 'Vul een geldig e-mailadres in.');
    }

    public function testVisitorIsLimitedToFiveMessagesAnHour(): void
    {
        // The test cache is cleared after every request, so the first five messages are counted on the limiter directly.
        $this->client->disableReboot();
        $limiter = static::getContainer()->get('limiter.contact_form');
        for ($i = 0; $i < 5; ++$i) {
            $this->assertTrue($limiter->create('127.0.0.1')->consume()->isAccepted());
        }

        $this->client->request('POST', '/contact', ['contact_form' => [
            'name' => 'Jan Jansen',
            'email' => 'jan@example.com',
            'message' => 'Nog een bericht na de limiet.',
        ]]);

        $this->assertResponseStatusCodeSame(429);
        $this->assertEmailCount(0);
    }

    public function testFooterLinksToContact(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('footer a[href="/contact"]');
    }
}
