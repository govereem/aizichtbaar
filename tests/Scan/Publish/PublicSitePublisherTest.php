<?php

declare(strict_types=1);

namespace App\Tests\Scan\Publish;

use App\Ai\AiUnavailableException;
use App\Entity\PublicSite;
use App\Entity\Scan;
use App\Scan\Ai\SiteSummarizer;
use App\Scan\Publish\PublicSiteGate;
use App\Scan\Publish\PublicSitePublisher;
use App\Scan\SiteFetcher;
use App\Tests\AppWebTestCase;
use App\Tests\Scan\StubAiClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;

class PublicSitePublisherTest extends AppWebTestCase
{
    private const SUMMARY = "Voorbeeld BV is een installatiebedrijf in Utrecht dat airco's, warmtepompen en onderhoud levert aan particulieren en kantoren. Het team werkt sinds 2009 in de regio en helpt klanten met advies, installatie en service na oplevering.";

    public function testIndexableSiteGetsAPublicPage(): void
    {
        $this->publisher(['https://voorbeeld.nl' => $this->homepage()], [$this->summary(true)])->publish($this->scan('https://voorbeeld.nl'));

        $site = $this->site('voorbeeld.nl');
        $this->assertSame(PublicSite::STATUS_INDEXABLE, $site->getStatus());
        $this->assertSame('voorbeeld-nl', $site->getSlug());
        $this->assertSame('Voorbeeld BV', $site->getName());
        $this->assertSame('installatiebedrijf', $site->getNiche());
        $this->assertSame(self::SUMMARY, $site->getSummary());
        $this->assertSame(72, $site->getScore());
    }

    public function testUnsafeSiteGetsNoPage(): void
    {
        $this->publisher(['https://voorbeeld.nl' => $this->homepage()], [$this->summary(false)])->publish($this->scan('https://voorbeeld.nl'));

        $this->assertNull($this->site('voorbeeld.nl'));
    }

    public function testFailedSummaryKeepsPageButMakesNoClaim(): void
    {
        $this->publisher(['https://voorbeeld.nl' => $this->homepage()], [new AiUnavailableException('Kapot.')])->publish($this->scan('https://voorbeeld.nl'));

        $site = $this->site('voorbeeld.nl');
        $this->assertSame(PublicSite::STATUS_NOINDEX, $site->getStatus());
        $this->assertNull($site->getSummary());
    }

    public function testSiteThatAsksToStayOutGetsNoPage(): void
    {
        $page = new MockResponse('<html><head><meta name="robots" content="noindex"></head><body>'.$this->words().'</body></html>');

        $this->publisher(['https://voorbeeld.nl' => $page], [$this->summary(true)])->publish($this->scan('https://voorbeeld.nl'));

        $this->assertNull($this->site('voorbeeld.nl'));
    }

    public function testRedirectToAnotherDomainGetsNoPage(): void
    {
        $page = new MockResponse($this->homepageHtml(), ['http_code' => 200, 'url' => 'https://voorbeeld.com/']);

        $this->publisher(['https://voorbeeld.nl' => $page], [$this->summary(true)])->publish($this->scan('https://voorbeeld.nl'));

        $this->assertNull($this->site('voorbeeld.nl'));
    }

    public function testSiteScannedInTheLastWeekIsNotSummarisedAgain(): void
    {
        $this->publisher(['https://voorbeeld.nl' => $this->homepage()], [$this->summary(true)])->publish($this->scan('https://voorbeeld.nl'));

        // The second run has no AI answer queued. Had it tried, the summary would be lost and the page would drop to noindex.
        $scan = $this->scan('https://voorbeeld.nl');
        $scan->setResults(['score' => 10, 'checks' => []]);
        $this->publisher(['https://voorbeeld.nl' => $this->homepage()], [])->publish($scan);

        $site = $this->site('voorbeeld.nl');
        $this->assertSame(72, $site->getScore());
        $this->assertSame(PublicSite::STATUS_INDEXABLE, $site->getStatus());
    }

    public function testRemovedSiteIsNeverPublishedAgain(): void
    {
        $removed = new PublicSite('voorbeeld.nl', 'voorbeeld-nl');
        $removed->setStatus(PublicSite::STATUS_REMOVED)->setScannedAt(new \DateTimeImmutable('-30 days'));
        $this->entityManager()->persist($removed);
        $this->entityManager()->flush();

        $this->publisher(['https://voorbeeld.nl' => $this->homepage()], [$this->summary(true)])->publish($this->scan('https://voorbeeld.nl'));

        $this->assertSame(PublicSite::STATUS_REMOVED, $this->site('voorbeeld.nl')->getStatus());
    }

    public function testApprovalModeKeepsNewPagesHidden(): void
    {
        $this->publisher(['https://voorbeeld.nl' => $this->homepage()], [$this->summary(true)], 'approval')->publish($this->scan('https://voorbeeld.nl'));

        $this->assertSame(PublicSite::STATUS_NOINDEX, $this->site('voorbeeld.nl')->getStatus());
    }

    /**
     * @param array<string, MockResponse> $responses keyed by URL without a trailing slash
     * @param list<array<mixed>|\Throwable> $aiAnswers
     */
    private function publisher(array $responses, array $aiAnswers, string $indexingMode = 'auto'): PublicSitePublisher
    {
        $client = new MockHttpClient(fn (string $method, string $url): MockResponse => $responses[rtrim($url, '/')] ?? new MockResponse('', ['http_code' => 404]));

        return new PublicSitePublisher(
            static::getContainer()->get(EntityManagerInterface::class),
            new SiteFetcher($client),
            new PublicSiteGate(['nl', 'de', 'be'], []),
            new SiteSummarizer(new StubAiClient($aiAnswers)),
            static::getContainer()->get(LockFactory::class),
            $indexingMode,
            new NullLogger(),
        );
    }

    private function scan(string $url): Scan
    {
        $scan = new Scan();
        $scan->setWebsiteUrl($url);
        $scan->setType('free');
        $scan->setStatus(Scan::STATUS_COMPLETED);
        $scan->setResults(['score' => 72, 'checks' => [['id' => 'llms_txt', 'title' => 'llms.txt', 'status' => 'warning', 'detail' => 'Geen llms.txt.', 'weight' => 1]]]);

        return $scan;
    }

    private function site(string $domain): ?PublicSite
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(PublicSite::class)->findOneBy(['domain' => $domain]);
    }

    private function homepage(): MockResponse
    {
        return new MockResponse($this->homepageHtml());
    }

    private function homepageHtml(): string
    {
        return '<html><head><title>Voorbeeld BV</title></head><body><p>'.$this->words().'</p></body></html>';
    }

    private function words(): string
    {
        return str_repeat('woord ', PublicSiteGate::MIN_WORDS);
    }

    /**
     * @return array{summary: string, niche: string, safe: bool}
     */
    private function summary(bool $safe): array
    {
        return ['summary' => $safe ? self::SUMMARY : '', 'niche' => $safe ? 'installatiebedrijf' : '', 'safe' => $safe];
    }
}
