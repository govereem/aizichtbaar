<?php

declare(strict_types=1);

namespace App\Scan;

/**
 * What the fetcher could download. A null field means that file was missing or unreachable.
 * finalUrl and xRobotsTag describe the homepage response: where it ended up after redirects, and its X-Robots-Tag header.
 */
final readonly class FetchedSite
{
    public function __construct(
        public string $url,
        public ?string $homepage = null,
        public ?string $robotsTxt = null,
        public ?string $llmsTxt = null,
        public ?string $sitemap = null,
        public ?string $finalUrl = null,
        public ?string $xRobotsTag = null,
    ) {
    }
}
