<?php

declare(strict_types=1);

namespace App\Scan\Publish;

use App\Scan\HtmlText;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Decides which scanned sites may get a public page. Every check here is deterministic and runs before any AI call.
 */
final readonly class PublicSiteGate
{
    public const MIN_WORDS = 150;

    /**
     * @param list<string> $allowedTlds top-level domains without the dot, e.g. ["nl", "de", "be"]
     * @param list<string> $blocklist   hosts that never get a public page
     */
    public function __construct(
        #[Autowire('%scan.public_tlds%')]
        private array $allowedTlds,
        #[Autowire('%scan.public_blocklist%')]
        private array $blocklist,
    ) {
    }

    /**
     * The public domain for a scanned URL, or null when the site must not get a public page.
     */
    public function domainFor(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        // An IP address or a single label such as "localhost" is not a public website.
        if (!str_contains($host, '.') || false !== filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $tld = substr((string) strrchr($host, '.'), 1);
        if (!in_array($tld, array_map('strtolower', $this->allowedTlds), true) || in_array($host, $this->blocklist, true)) {
            return null;
        }

        return $host;
    }

    /**
     * A parked or empty domain has almost no text. Such a page would say nothing useful.
     */
    public function hasEnoughText(string $homepage): bool
    {
        return HtmlText::wordCount(HtmlText::visibleText($homepage)) >= self::MIN_WORDS;
    }

    /**
     * True when the site itself asks search engines to stay out, by meta tag, header or robots.txt.
     */
    public function asksToStayOut(string $homepage, ?string $robotsTxt, ?string $xRobotsTag = null): bool
    {
        $metaRobots = (new Crawler($homepage))->filter('meta[name="robots"]')->each(
            fn (Crawler $meta): string => strtolower((string) $meta->attr('content')),
        );

        if (array_any($metaRobots, fn (string $content): bool => str_contains($content, 'noindex'))) {
            return true;
        }

        if (null !== $xRobotsTag && str_contains(strtolower($xRobotsTag), 'noindex')) {
            return true;
        }

        // A blanket "Disallow: /" tells crawlers to stay out of the whole site.
        return null !== $robotsTxt && 1 === preg_match('#^\s*Disallow:\s*/\s*$#mi', $robotsTxt);
    }
}
