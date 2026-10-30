<?php

declare(strict_types=1);

namespace App\Tests\Scan\Ai;

use App\Ai\AiUnavailableException;
use App\Scan\Ai\SiteSummarizer;
use App\Tests\Scan\StubAiClient;
use PHPUnit\Framework\TestCase;

class SiteSummarizerTest extends TestCase
{
    private const SUMMARY = "Voorbeeld BV is een installatiebedrijf in Utrecht dat airco's, warmtepompen en onderhoud levert aan particulieren en kantoren. Het team werkt sinds 2009 in de regio en helpt klanten met advies, installatie en service na oplevering.";

    public function testValidAnswerIsReturned(): void
    {
        $summarizer = new SiteSummarizer(new StubAiClient([['summary' => self::SUMMARY, 'niche' => 'installatiebedrijf', 'safe' => true]]));

        $profile = $summarizer->summarize('<html><body><p>Voorbeeld BV</p></body></html>');

        $this->assertTrue($profile['safe']);
        $this->assertSame('installatiebedrijf', $profile['niche']);
        $this->assertSame(self::SUMMARY, $profile['summary']);
    }

    public function testUnsafeSiteIsReportedNotThrown(): void
    {
        $summarizer = new SiteSummarizer(new StubAiClient([['summary' => '', 'niche' => '', 'safe' => false]]));

        $this->assertFalse($summarizer->summarize('<html></html>')['safe']);
    }

    public function testSummaryWithUrlOrPhoneNumberIsRejected(): void
    {
        $withUrl = str_replace('Utrecht', 'Utrecht via https://voorbeeld.nl', self::SUMMARY);
        $summarizer = new SiteSummarizer(new StubAiClient([['summary' => $withUrl, 'niche' => 'x', 'safe' => true]]));

        $this->expectException(AiUnavailableException::class);

        $summarizer->summarize('<html></html>');
    }

    public function testMissingAnswerIsUnavailable(): void
    {
        $summarizer = new SiteSummarizer(new StubAiClient());

        $this->expectException(AiUnavailableException::class);

        $summarizer->summarize('<html></html>');
    }
}
