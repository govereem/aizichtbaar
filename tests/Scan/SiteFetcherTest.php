<?php

declare(strict_types=1);

namespace App\Tests\Scan;

use App\Scan\SiteFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SiteFetcherTest extends TestCase
{
    public function testFetchesHomepageAndSiteFilesFromTheOrigin(): void
    {
        $requested = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$requested) {
            $requested[] = $url;

            return match ($url) {
                'https://voorbeeld.nl/pagina?ref=1' => new MockResponse('<html>home</html>'),
                'https://voorbeeld.nl/robots.txt' => new MockResponse("User-agent: *\nDisallow:"),
                default => new MockResponse('', ['http_code' => 404]),
            };
        });

        $site = (new SiteFetcher($client))->fetch('https://voorbeeld.nl/pagina?ref=1');

        $this->assertSame('<html>home</html>', $site->homepage);
        $this->assertStringContainsString('Disallow:', (string) $site->robotsTxt);
        $this->assertNull($site->llmsTxt, 'A 404 must not count as a file.');
        $this->assertContains('https://voorbeeld.nl/sitemap.xml', $requested);
    }

    public function testRejectsNonHttpUrls(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SiteFetcher(new MockHttpClient()))->fetch('file:///etc/passwd');
    }

    public function testPrivateAddressesAreNeverRequested(): void
    {
        $requests = 0;
        $inner = new MockHttpClient(function () use (&$requests) {
            ++$requests;

            return new MockResponse('secret');
        });

        $site = (new SiteFetcher(new NoPrivateNetworkHttpClient($inner)))->fetch('http://127.0.0.1/admin');

        $this->assertNull($site->homepage);
        $this->assertSame(0, $requests, 'The guard must refuse before the request leaves the client.');
    }
}
