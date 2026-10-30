<?php

declare(strict_types=1);

namespace App\Scan;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Picks the key pages of a site, from its sitemap first and then from the homepage links. Same origin only.
 */
final readonly class SiteCrawler
{
    public const MAX_PAGES = 6;

    private const SKIPPED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'zip', 'xml', 'mp4'];

    public function __construct(
        private SiteFetcher $fetcher,
    ) {
    }

    /**
     * The homepage comes first, followed by up to MAX_PAGES - 1 other pages that returned HTML.
     *
     * @return list<array{url: string, html: string}>
     */
    public function crawl(FetchedSite $site): array
    {
        $home = $this->normalize($site->url);
        $pages = [$home => $site->homepage ?? ''];

        $candidates = array_merge(
            $this->sitemapUrls($site->sitemap),
            $this->linkedUrls($home, $site->homepage),
        );

        foreach ($candidates as $url) {
            if (count($pages) >= self::MAX_PAGES) {
                break;
            }

            if (isset($pages[$url]) || !$this->isSameSite($home, $url)) {
                continue;
            }

            $html = $this->fetcher->fetchPage($url);
            if (null !== $html && '' !== trim($html)) {
                $pages[$url] = $html;
            }
        }

        return array_map(
            fn (string $url, string $html): array => ['url' => $url, 'html' => $html],
            array_keys($pages),
            array_values($pages),
        );
    }

    /**
     * @return list<string>
     */
    private function sitemapUrls(?string $sitemap): array
    {
        if (null === $sitemap || !preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#', $sitemap, $matches)) {
            return [];
        }

        return array_map(fn (string $url): string => $this->normalize($url), $matches[1]);
    }

    /**
     * @return list<string>
     */
    private function linkedUrls(string $home, ?string $html): array
    {
        if (null === $html) {
            return [];
        }

        $hrefs = (new Crawler($html))->filter('a[href]')->each(fn (Crawler $link): string => (string) $link->attr('href'));
        $origin = rtrim($home, '/');

        $urls = [];
        foreach ($hrefs as $href) {
            // Relative paths are resolved against the origin. Anchors, mail and phone links are not pages.
            if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
                $href = $origin.$href;
            }

            if (!str_starts_with($href, 'http://') && !str_starts_with($href, 'https://')) {
                continue;
            }

            $urls[] = $this->normalize($href);
        }

        return array_values(array_unique($urls));
    }

    private function normalize(string $url): string
    {
        $withoutFragment = explode('#', trim($url), 2)[0];

        return rtrim($withoutFragment, '/');
    }

    private function isSameSite(string $home, string $url): bool
    {
        $homeParts = parse_url($home);
        $parts = parse_url($url);

        if (!in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }

        $extension = strtolower(pathinfo($parts['path'] ?? '', PATHINFO_EXTENSION));

        return strcasecmp($parts['host'] ?? '', $homeParts['host'] ?? '') === 0
            && !in_array($extension, self::SKIPPED_EXTENSIONS, true);
    }
}
