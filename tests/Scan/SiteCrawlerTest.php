<?php

declare(strict_types=1);

namespace App\Tests\Scan;

use App\Scan\FetchedSite;
use App\Scan\SiteCrawler;
use App\Scan\SiteFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SiteCrawlerTest extends TestCase
{
    private const HOME = '<html><body><a href="/diensten">Diensten</a><a href="https://extern.nl/x">Extern</a><a href="/brochure.pdf">PDF</a><a href="#top">Top</a><a href="/diensten#prijs">Prijs</a></body></html>';

    public function testHomepageComesFirstAndOnlySameOriginPagesAreAdded(): void
    {
        $requested = [];
        $crawler = $this->crawler(function (string $method, string $url) use (&$requested) {
            $requested[] = $url;

            return new MockResponse('<html><body><p>Pagina</p></body></html>');
        });

        $pages = $crawler->crawl(new FetchedSite(url: 'https://voorbeeld.nl', homepage: self::HOME));

        $this->assertSame(['https://voorbeeld.nl', 'https://voorbeeld.nl/diensten'], array_column($pages, 'url'));
        $this->assertSame(['https://voorbeeld.nl/diensten'], $requested);
    }

    public function testSitemapUrlsAreUsedAndTheCapIsRespected(): void
    {
        $locs = array_map(fn (int $i): string => sprintf('<url><loc>https://voorbeeld.nl/pagina-%d</loc></url>', $i), range(1, 10));
        $sitemap = '<urlset>'.implode('', $locs).'<url><loc>https://ander.nl/x</loc></url></urlset>';

        $crawler = $this->crawler(fn (string $method, string $url) => new MockResponse('<html><body>Tekst</body></html>'));

        $pages = $crawler->crawl(new FetchedSite(url: 'https://voorbeeld.nl', homepage: '<html></html>', sitemap: $sitemap));

        $this->assertCount(SiteCrawler::MAX_PAGES, $pages);
        $this->assertSame('https://voorbeeld.nl/pagina-1', $pages[1]['url']);
        $this->assertNotContains('https://ander.nl/x', array_column($pages, 'url'));
    }

    public function testUnreachablePagesAreLeftOut(): void
    {
        $crawler = $this->crawler(fn (string $method, string $url) => new MockResponse('', ['http_code' => 404]));

        $pages = $crawler->crawl(new FetchedSite(url: 'https://voorbeeld.nl', homepage: self::HOME));

        $this->assertSame(['https://voorbeeld.nl'], array_column($pages, 'url'));
    }

    private function crawler(callable $responder): SiteCrawler
    {
        return new SiteCrawler(new SiteFetcher(new MockHttpClient($responder)));
    }
}
