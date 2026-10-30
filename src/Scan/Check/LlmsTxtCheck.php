<?php

declare(strict_types=1);

namespace App\Scan\Check;

use App\Scan\CheckResult;
use App\Scan\FetchedSite;
use App\Scan\ScanCheckInterface;

final readonly class LlmsTxtCheck implements ScanCheckInterface
{
    public function check(FetchedSite $site): CheckResult
    {
        $llms = trim((string) $site->llmsTxt);

        // A missing llms.txt often returns an HTML 404 page, which must not count as present.
        if ('' === $llms || str_contains(strtolower($llms), '<html')) {
            return new CheckResult('llms_txt', 'llms.txt', CheckResult::WARNING, 'Geen llms.txt gevonden. Die geeft AI-systemen een korte, gerichte samenvatting van je site.', 1);
        }

        if (!str_starts_with($llms, '#')) {
            return new CheckResult('llms_txt', 'llms.txt', CheckResult::WARNING, 'llms.txt is aanwezig, maar begint niet met een titel (#). Volg het formaat van llmstxt.org.', 1);
        }

        return new CheckResult('llms_txt', 'llms.txt', CheckResult::OK, 'llms.txt is aanwezig.', 1);
    }
}
