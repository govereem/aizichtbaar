<?php

declare(strict_types=1);

namespace App\Tests\Scan;

use App\Scan\Check\AiCrawlerAccessCheck;
use App\Scan\Check\RenderabilityCheck;
use App\Scan\FetchedSite;
use App\Scan\ReadyToPasteContent;
use App\Scan\ScanRunner;
use App\Scan\ScoreCalculator;
use App\Scan\SiteFetcher;
use App\Scan\SiteUnreachableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ScanRunnerTest extends TestCase
{
    public function testUnreachableHomepageFailsInsteadOfScoring(): void
    {
        $runner = $this->runner(new MockResponse('', ['http_code' => 404]));

        $this->expectException(SiteUnreachableException::class);

        $runner->run('https://voorbeeld.nl');
    }

    public function testReachableSiteGivesScoreChecksAndActionsForFailures(): void
    {
        $runner = $this->runner(new MockResponse('<html><body><p>Kort.</p></body></html>'));

        $result = $runner->run('https://voorbeeld.nl');

        // No robots.txt means crawlers are allowed (pass). One word of HTML means nothing is readable without JavaScript (fail).
        $this->assertCount(2, $result['checks']);
        $this->assertSame('1 van 2 controles geslaagd.', $result['summary']);
        $this->assertTrue($result['basic_advice']);
        $this->assertNotEmpty($result['actions']);
    }

    public function testFreeResultIncludesReadyToPasteFiles(): void
    {
        $runner = $this->runner(new MockResponse('<html><head><title>Voorbeeld</title></head><body><p>Kort.</p></body></html>'));

        $content = $runner->run('https://voorbeeld.nl')['content'];

        $this->assertStringContainsString('# Voorbeeld', $content['llms_txt']);
        $this->assertStringContainsString('"@type": "Organization"', $content['organization_json_ld']);
        $this->assertStringContainsString('User-agent: GPTBot', $content['robots_snippet']);
    }

    public function testEvaluateLeavesOutReadyToPasteFiles(): void
    {
        $runner = $this->runner(new MockResponse('<html><body><p>Kort.</p></body></html>'));

        $this->assertArrayNotHasKey('content', $runner->evaluate(new FetchedSite('https://voorbeeld.nl', homepage: '<html></html>')));
    }

    private function runner(MockResponse $homepage): ScanRunner
    {
        // The homepage is the first request. Every later file (robots.txt, llms.txt, sitemap) gets a 404.
        $responses = [$homepage];
        $client = new MockHttpClient(function () use (&$responses) {
            return array_shift($responses) ?? new MockResponse('', ['http_code' => 404]);
        });

        return new ScanRunner(
            new SiteFetcher($client),
            [new AiCrawlerAccessCheck(), new RenderabilityCheck()],
            new ScoreCalculator(),
            new ReadyToPasteContent(),
        );
    }
}
