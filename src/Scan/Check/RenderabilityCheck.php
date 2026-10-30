<?php

declare(strict_types=1);

namespace App\Scan\Check;

use App\Scan\CheckResult;
use App\Scan\FetchedSite;
use App\Scan\HtmlText;
use App\Scan\ScanCheckInterface;

final readonly class RenderabilityCheck implements ScanCheckInterface
{
    public function check(FetchedSite $site): CheckResult
    {
        if (null === $site->homepage) {
            return new CheckResult('renderable', 'Leesbaar zonder JavaScript', CheckResult::ERROR, 'De homepage kon niet worden opgehaald, dus de leesbaarheid is niet gecontroleerd.', 2);
        }

        // Most AI crawlers read the raw HTML and do not run JavaScript.
        $words = HtmlText::wordCount(HtmlText::visibleText($site->homepage));
        $detail = sprintf('De homepage bevat %d woorden in de ruwe HTML.', $words);

        if ($words >= 300) {
            return new CheckResult('renderable', 'Leesbaar zonder JavaScript', CheckResult::OK, $detail, 2);
        }

        if ($words >= 100) {
            return new CheckResult('renderable', 'Leesbaar zonder JavaScript', CheckResult::WARNING, $detail, 2);
        }

        return new CheckResult('renderable', 'Leesbaar zonder JavaScript', CheckResult::ERROR, $detail.' Veel content wordt waarschijnlijk pas met JavaScript geladen, en dat zien AI-crawlers vaak niet.', 2);
    }
}
