<?php

declare(strict_types=1);

namespace App\Tests\Scan;

use App\Scan\Ai\ActionPlanWriter;
use App\Scan\Ai\AnswerSimulator;
use App\Scan\Ai\ContentSuggester;
use App\Scan\Check\AiCrawlerAccessCheck;
use App\Scan\Check\ContentStructureCheck;
use App\Scan\Check\LlmsTxtCheck;
use App\Scan\Check\RenderabilityCheck;
use App\Scan\Check\SitemapCheck;
use App\Scan\CompetitorComparer;
use App\Scan\ExtendedScanRunner;
use App\Scan\ReadyToPasteContent;
use App\Scan\ScanRunner;
use App\Scan\ScoreCalculator;
use App\Scan\SiteCrawler;
use App\Scan\SiteFetcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ExtendedScanRunnerTest extends TestCase
{
    private const HOME = '<html><head><title>Voorbeeld</title></head><body><h1>Voorbeeld</h1>'
        .'<a href="/diensten">Diensten</a><a href="/over-ons">Over ons</a></body></html>';

    public function testWithoutAiTheDeterministicPartsStillShipAndTheScanIsMarkedDegraded(): void
    {
        $result = $this->runner(new StubAiClient())->run('https://voorbeeld.nl', []);

        $this->assertTrue($result['degraded']);
        $this->assertSame(['simulation', 'actions', 'rewrites'], $result['degraded_steps']);
        $this->assertNull($result['simulation']);
        $this->assertNull($result['rewrites']);
        $this->assertTrue($result['basic_advice']);
        $this->assertCount(3, $result['pages']);
        $this->assertNotEmpty($result['actions']);
        $this->assertSame('', $result['actions'][0]['why']);
        $this->assertStringStartsWith('# Voorbeeld', $result['content']['llms_txt']);
    }

    public function testWithAiTheSimulationAndPlansAreAttached(): void
    {
        $simulation = ['niche' => 'tandarts', 'questions' => [['question' => 'Waar is een tandarts in Utrecht?', 'answer' => 'Voorbeeld.', 'verdict' => 'cited', 'reason' => 'Genoemd op de homepage.']]];
        $plans = ['actions' => [['id' => 'llms_txt', 'why' => 'AI leest dit bestand als eerste.', 'steps' => ['Maak llms.txt aan.'], 'effort' => 'laag']]];

        $rewrites = ['rewrites' => [['url' => 'https://voorbeeld.nl', 'heading' => 'Voorbeeld', 'answer' => 'Antwoord.']]];

        $result = $this->runner(new StubAiClient([$simulation, $plans, $rewrites]))->run('https://voorbeeld.nl', []);

        $this->assertFalse($result['degraded']);
        $this->assertSame([], $result['degraded_steps']);
        $this->assertFalse($result['basic_advice']);
        $this->assertSame('tandarts', $result['simulation']['niche']);
        $this->assertSame('Antwoord.', $result['rewrites'][0]['answer']);

        $llms = array_values(array_filter($result['actions'], fn (array $action): bool => 'llms_txt' === $action['id']));
        $this->assertSame('AI leest dit bestand als eerste.', $llms[0]['why']);
        $this->assertSame(['Maak llms.txt aan.'], $llms[0]['steps']);
    }

    public function testCompetitorsGapsAreTheChecksTheyPassAndWeFail(): void
    {
        $result = $this->runner(new StubAiClient())->run('https://voorbeeld.nl', ['https://concurrent.nl', 'https://onbereikbaar.nl']);

        $this->assertSame(['llms.txt'], $result['competitors'][0]['gaps']);
        $this->assertNotNull($result['competitors'][1]['error']);
        $this->assertNull($result['competitors'][1]['score']);
    }

    private function runner(StubAiClient $ai): ExtendedScanRunner
    {
        $responses = [
            'https://voorbeeld.nl' => new MockResponse(self::HOME),
            'https://voorbeeld.nl/diensten' => new MockResponse('<html><body><p>Onze diensten.</p></body></html>'),
            'https://voorbeeld.nl/over-ons' => new MockResponse('<html><body><p>Over ons.</p></body></html>'),
            'https://concurrent.nl' => new MockResponse('<html><body><h1>Concurrent</h1></body></html>'),
            'https://concurrent.nl/llms.txt' => new MockResponse('# Concurrent'),
        ];

        // Anything not listed (robots.txt, sitemap.xml, unknown competitor) is a 404.
        $client = new MockHttpClient(fn (string $method, string $url): MockResponse => $responses[rtrim($url, '/')] ?? new MockResponse('', ['http_code' => 404]));

        $fetcher = new SiteFetcher($client);
        $scanRunner = new ScanRunner($fetcher, [new AiCrawlerAccessCheck(), new RenderabilityCheck(), new ContentStructureCheck(), new LlmsTxtCheck(), new SitemapCheck()], new ScoreCalculator(), new ReadyToPasteContent());
        $logger = new NullLogger();

        return new ExtendedScanRunner(
            $fetcher,
            new SiteCrawler($fetcher),
            $scanRunner,
            new AnswerSimulator($ai),
            new ActionPlanWriter($ai),
            new ContentSuggester($ai, $logger),
            new CompetitorComparer($scanRunner),
            new ReadyToPasteContent(),
            $logger,
        );
    }
}
