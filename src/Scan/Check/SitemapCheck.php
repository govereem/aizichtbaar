<?php

declare(strict_types=1);

namespace App\Scan\Check;

use App\Scan\CheckResult;
use App\Scan\FetchedSite;
use App\Scan\ScanCheckInterface;

final readonly class SitemapCheck implements ScanCheckInterface
{
    public function check(FetchedSite $site): CheckResult
    {
        if (null === $site->sitemap || !preg_match('/<(urlset|sitemapindex)\b/i', $site->sitemap)) {
            return new CheckResult('sitemap', 'Sitemap', CheckResult::WARNING, 'Geen sitemap.xml gevonden. Zonder sitemap vinden crawlers je pagina\'s minder snel.', 1);
        }

        $urls = preg_match_all('/<loc>/i', $site->sitemap);

        return new CheckResult('sitemap', 'Sitemap', CheckResult::OK, sprintf('sitemap.xml aanwezig met %d locaties.', $urls), 1);
    }
}
