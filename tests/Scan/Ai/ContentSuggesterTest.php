<?php

declare(strict_types=1);

namespace App\Tests\Scan\Ai;

use App\Ai\AiUnavailableException;
use App\Scan\Ai\ContentSuggester;
use App\Tests\Scan\StubAiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ContentSuggesterTest extends TestCase
{
    private const PAGES = [
        ['url' => 'https://voorbeeld.nl', 'html' => '<html><body><h1>Tandarts Jansen</h1><h2>Wat kost een controle?</h2><p>Kort.</p></body></html>'],
        ['url' => 'https://voorbeeld.nl/tarieven', 'html' => '<html><body><h1>Tarieven</h1></body></html>'],
    ];

    public function testOnlyRewritesForFetchedPagesAreKeptAndCapped(): void
    {
        $rewrites = [
            ['url' => 'https://voorbeeld.nl', 'heading' => 'Wat kost een controle?', 'answer' => 'Een controle kost €65.'],
            ['url' => 'https://verzonnen.nl/prijs', 'heading' => 'Prijs', 'answer' => 'Verzonnen pagina.'],
            ['url' => 'https://voorbeeld.nl/tarieven', 'heading' => 'Tarieven', 'answer' => '   '],
            ['url' => 'https://voorbeeld.nl/tarieven', 'heading' => 'Tarieven', 'answer' => 'Tweede.'],
            ['url' => 'https://voorbeeld.nl', 'heading' => 'Extra', 'answer' => 'Derde.'],
            ['url' => 'https://voorbeeld.nl', 'heading' => 'Vierde', 'answer' => 'Vierde.'],
        ];

        $result = (new ContentSuggester(new StubAiClient([['rewrites' => $rewrites]]), new NullLogger()))->suggest(self::PAGES);

        $this->assertCount(ContentSuggester::MAX_REWRITES, $result);
        $this->assertSame(['https://voorbeeld.nl', 'https://voorbeeld.nl/tarieven', 'https://voorbeeld.nl'], array_column($result, 'url'));
        $this->assertSame('Een controle kost €65.', $result[0]['answer']);
    }

    public function testNoUsableRewriteIsUnavailable(): void
    {
        $suggester = new ContentSuggester(new StubAiClient([['rewrites' => [['url' => 'https://verzonnen.nl', 'answer' => 'x']]]]), new NullLogger());

        $this->expectException(AiUnavailableException::class);

        $suggester->suggest(self::PAGES);
    }

    public function testHeadingsAreSentAsPartOfTheSiteData(): void
    {
        $client = new StubAiClient([['rewrites' => [['url' => 'https://voorbeeld.nl', 'heading' => 'x', 'answer' => 'y']]]]);

        (new ContentSuggester($client, new NullLogger()))->suggest(self::PAGES);

        $this->assertStringStartsWith('<pages>', $client->lastUserMessage);
        $this->assertStringContainsString('Headings: Tandarts Jansen | Wat kost een controle?', $client->lastUserMessage);
    }
}
