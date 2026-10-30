<?php

declare(strict_types=1);

namespace App\Tests\Scan;

use App\Scan\ReadyToPasteContent;
use App\Scan\SiteMeta;
use PHPUnit\Framework\TestCase;

class ReadyToPasteContentTest extends TestCase
{
    public function testLlmsTxtStartsWithTitleAndListsPages(): void
    {
        $home = new SiteMeta(title: 'Tandarts Jansen', description: 'Tandartspraktijk in Utrecht.', siteName: 'Tandarts Jansen');
        $pages = ['https://voorbeeld.nl/tarieven' => new SiteMeta(title: 'Tarieven', description: 'Wat kost een controle?')];

        $text = (new ReadyToPasteContent())->llmsTxt('https://voorbeeld.nl', $home, $pages);

        $this->assertStringStartsWith("# Tandarts Jansen\n\n> Tandartspraktijk in Utrecht.\n", $text);
        $this->assertStringContainsString('- [Tarieven](https://voorbeeld.nl/tarieven): Wat kost een controle?', $text);
    }

    public function testOrganizationJsonLdIsValidAndOmitsEmptyFields(): void
    {
        $home = new SiteMeta(title: 'Voorbeeld', sameAs: ['https://www.linkedin.com/company/voorbeeld']);

        $json = (new ReadyToPasteContent())->organizationJsonLd('https://voorbeeld.nl', $home);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Organization', $data['@type']);
        $this->assertSame('Voorbeeld', $data['name']);
        $this->assertSame(['https://www.linkedin.com/company/voorbeeld'], $data['sameAs']);
        $this->assertArrayNotHasKey('description', $data);
    }

    public function testOrganizationJsonLdShowsSameAsExamplesWhenNoProfilesWereFound(): void
    {
        $json = (new ReadyToPasteContent())->organizationJsonLd('https://voorbeeld.nl', new SiteMeta(title: 'Voorbeeld'));
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertContains('https://www.youtube.com/@jouw-bedrijf', $data['sameAs']);
        $this->assertContains('https://www.instagram.com/jouw-bedrijf', $data['sameAs']);
    }

    public function testRobotsSnippetAllowsTheAiCrawlers(): void
    {
        $snippet = (new ReadyToPasteContent())->robotsSnippet();

        $this->assertStringContainsString("User-agent: GPTBot\nAllow: /", $snippet);
        $this->assertStringContainsString('User-agent: PerplexityBot', $snippet);
    }

    public function testSiteMetaReadsTitleDescriptionAndSocialLinks(): void
    {
        $html = '<html><head><title>Voorbeeld BV</title><meta name="description" content="Wij doen dit.">'
            .'<meta property="og:site_name" content="Voorbeeld"></head>'
            .'<body><a href="https://nl.linkedin.com/company/voorbeeld">LinkedIn</a><a href="https://voorbeeld.nl/contact">Contact</a></body></html>';

        $meta = SiteMeta::fromHtml($html);

        $this->assertSame('Voorbeeld BV', $meta->title);
        $this->assertSame('Wij doen dit.', $meta->description);
        $this->assertSame('Voorbeeld', $meta->name('https://voorbeeld.nl'));
        $this->assertSame(['https://nl.linkedin.com/company/voorbeeld'], $meta->sameAs);
    }
}
