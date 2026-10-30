<?php

declare(strict_types=1);

namespace App\Scan;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Facts about a page that the generated content (llms.txt, Organization JSON-LD) is built from.
 */
final readonly class SiteMeta
{
    private const SOCIAL_HOSTS = ['facebook.com', 'instagram.com', 'linkedin.com', 'x.com', 'twitter.com', 'youtube.com', 'tiktok.com'];

    /**
     * @param list<string> $sameAs
     */
    public function __construct(
        public string $title = '',
        public string $description = '',
        public string $siteName = '',
        public array $sameAs = [],
    ) {
    }

    public static function fromHtml(?string $html): self
    {
        if (null === $html || '' === trim($html)) {
            return new self();
        }

        $crawler = new Crawler($html);

        return new self(
            title: self::text($crawler, 'title'),
            description: self::attribute($crawler, 'meta[name="description"]', 'content'),
            siteName: self::attribute($crawler, 'meta[property="og:site_name"]', 'content'),
            sameAs: self::socialLinks($crawler),
        );
    }

    /**
     * The name a reader would recognise: the site name if set, otherwise the page title.
     */
    public function name(string $fallback): string
    {
        return '' !== $this->siteName ? $this->siteName : ('' !== $this->title ? $this->title : $fallback);
    }

    private static function text(Crawler $crawler, string $selector): string
    {
        return $crawler->filter($selector)->count() > 0 ? trim($crawler->filter($selector)->text()) : '';
    }

    private static function attribute(Crawler $crawler, string $selector, string $name): string
    {
        return $crawler->filter($selector)->count() > 0 ? trim((string) $crawler->filter($selector)->attr($name)) : '';
    }

    /**
     * @return list<string>
     */
    private static function socialLinks(Crawler $crawler): array
    {
        $links = $crawler->filter('a[href]')->each(fn (Crawler $link): string => (string) $link->attr('href'));

        $social = array_filter($links, function (string $href): bool {
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));

            foreach (self::SOCIAL_HOSTS as $allowed) {
                if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                    return true;
                }
            }

            return false;
        });

        return array_values(array_unique($social));
    }
}
