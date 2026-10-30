<?php

declare(strict_types=1);

namespace App\Scan;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SiteFetcher
{
    private const MAX_BYTES = 1_000_000;

    public function __construct(
        // Must block private and loopback addresses, including after redirects (see services.yaml).
        #[Autowire(service: 'app.scan.http_client')]
        private HttpClientInterface $client,
    ) {
    }

    public function fetch(string $url): FetchedSite
    {
        $parts = parse_url($url);
        if (!in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            throw new \InvalidArgumentException(sprintf('Ongeldige site-URL: %s', $url));
        }

        $origin = sprintf('%s://%s', $parts['scheme'], $parts['host']);
        $homepage = $this->get($url);

        return new FetchedSite(
            url: $url,
            homepage: $homepage['body'] ?? null,
            robotsTxt: $this->fetchPage($origin.'/robots.txt'),
            llmsTxt: $this->fetchPage($origin.'/llms.txt'),
            sitemap: $this->fetchPage($origin.'/sitemap.xml'),
            finalUrl: $homepage['finalUrl'] ?? null,
            xRobotsTag: $homepage['xRobotsTag'] ?? null,
        );
    }

    public function fetchPage(string $url): ?string
    {
        return $this->get($url)['body'] ?? null;
    }

    /**
     * @return array{body: string, finalUrl: string, xRobotsTag: ?string}|null null when the file is missing or unreachable
     */
    private function get(string $url): ?array
    {
        try {
            $response = $this->client->request('GET', $url, [
                'timeout' => 10,
                'max_duration' => 20,
                'headers' => ['User-Agent' => 'AIzichtbaarScan/1.0 (+https://aizichtbaar.nl)'],
            ]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            return [
                'body' => substr($response->getContent(false), 0, self::MAX_BYTES),
                'finalUrl' => (string) $response->getInfo('url'),
                'xRobotsTag' => $response->getHeaders(false)['x-robots-tag'][0] ?? null,
            ];
        } catch (TransportExceptionInterface) {
            return null;
        }
    }
}
