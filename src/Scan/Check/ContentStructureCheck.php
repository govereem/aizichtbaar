<?php

declare(strict_types=1);

namespace App\Scan\Check;

use App\Scan\CheckResult;
use App\Scan\FetchedSite;
use App\Scan\HtmlText;
use App\Scan\ScanCheckInterface;
use Symfony\Component\DomCrawler\Crawler;

final readonly class ContentStructureCheck implements ScanCheckInterface
{
    public function check(FetchedSite $site): CheckResult
    {
        if (null === $site->homepage) {
            return new CheckResult('content_structure', 'Contentstructuur', CheckResult::ERROR, 'De homepage kon niet worden opgehaald, dus de contentstructuur is niet gecontroleerd.', 2);
        }

        $crawler = new Crawler($site->homepage);
        $h1 = $crawler->filter('h1')->count();
        $h2 = $crawler->filter('h2')->count();
        $answerFirst = count(array_filter($crawler->filter('h2')->each(fn (Crawler $heading): bool => $this->isAnswerFirst($heading))));

        if (0 === $h1 && 0 === $h2) {
            return new CheckResult('content_structure', 'Contentstructuur', CheckResult::ERROR, 'Geen koppen (H1/H2) gevonden. AI-systemen kunnen de opbouw van je content dan niet volgen.', 2);
        }

        $detail = sprintf('%d H1-kop(pen), %d H2-kop(pen), %d kop(pen) gevolgd door een direct antwoord.', $h1, $h2, $answerFirst);

        if (1 === $h1 && $h2 >= 2 && $answerFirst >= 1) {
            return new CheckResult('content_structure', 'Contentstructuur', CheckResult::OK, $detail, 2);
        }

        return new CheckResult('content_structure', 'Contentstructuur', CheckResult::WARNING, $detail, 2);
    }

    /**
     * A heading followed directly by a paragraph of 15-80 words, the length of a short, quotable answer.
     */
    private function isAnswerFirst(Crawler $heading): bool
    {
        $next = $heading->nextAll()->first();
        if (0 === $next->count() || 'p' !== $next->nodeName()) {
            return false;
        }

        $words = HtmlText::wordCount($next->text());

        return $words >= 15 && $words <= 80;
    }
}
