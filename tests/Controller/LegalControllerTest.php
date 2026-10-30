<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;

class LegalControllerTest extends AppWebTestCase
{
    public function testTermsPageIsPublicAndShowsCompanyDetails(): void
    {
        $this->client->request('GET', '/algemene-voorwaarden');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Algemene voorwaarden');
        $this->assertSelectorTextContains('body', '82005222');
        $this->assertSelectorTextContains('body', 'Leeuwarden');
        $this->assertSelectorTextContains('body', 'Gerben Colmjonwei 109');
        $this->assertSelectorTextContains('body', 'support@aizichtbaar.nl');
    }

    public function testTermsPageLimitsAiLiability(): void
    {
        $this->client->request('GET', '/algemene-voorwaarden');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'AI-resultaten en zoekmachines');
        $this->assertSelectorTextContains('body', 'niet aansprakelijk voor');
    }

    public function testTermsPageHasNoBankTransferText(): void
    {
        $this->client->request('GET', '/algemene-voorwaarden');

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsStringIgnoringCase('overschrijving', $this->client->getResponse()->getContent());
    }

    public function testFooterLinksToTerms(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('footer a[href="/algemene-voorwaarden"]');
    }

    public function testPrivacyPageIsPublicAndShowsCompanyDetails(): void
    {
        $this->client->request('GET', '/privacybeleid');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Privacybeleid');
        $this->assertSelectorTextContains('body', '82005222');
        $this->assertSelectorTextContains('body', 'Gerben Colmjonwei 109');
        $this->assertSelectorTextContains('body', 'support@aizichtbaar.nl');
    }

    public function testPrivacyPageExplainsRightsAndComplaintBody(): void
    {
        $this->client->request('GET', '/privacybeleid');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Autoriteit Persoonsgegevens');
        $this->assertSelectorTextContains('body', 'binnen vier weken');
    }

    public function testFooterLinksToPrivacy(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('footer a[href="/privacybeleid"]');
    }
}
