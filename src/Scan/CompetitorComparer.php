<?php

declare(strict_types=1);

namespace App\Scan;

/**
 * Runs the same checks on competitor homepages and lists what they do that the customer's site does not.
 */
final readonly class CompetitorComparer
{
    public function __construct(
        private ScanRunner $runner,
    ) {
    }

    /**
     * @param list<string>                             $urls
     * @param list<array{id: string, status: string}>  $ownChecks
     *
     * @return list<array{url: string, score: ?int, gaps: list<string>, error: ?string}>
     */
    public function compare(array $urls, array $ownChecks): array
    {
        $comparison = [];
        foreach ($urls as $url) {
            try {
                $result = $this->runner->run($url);
            } catch (SiteUnreachableException|\InvalidArgumentException) {
                $comparison[] = ['url' => $url, 'score' => null, 'gaps' => [], 'error' => 'Deze website kon niet worden bekeken.'];

                continue;
            }

            $comparison[] = [
                'url' => $url,
                'score' => $result['score'],
                'gaps' => $this->gaps($ownChecks, $result['checks']),
                'error' => null,
            ];
        }

        return $comparison;
    }

    /**
     * A gap is a check that the customer fails and the competitor passes.
     *
     * @param list<array{id: string, status: string}> $ownChecks
     * @param list<array{id: string, title: string, status: string}> $competitorChecks
     *
     * @return list<string>
     */
    private function gaps(array $ownChecks, array $competitorChecks): array
    {
        $passing = [];
        foreach ($competitorChecks as $check) {
            if (CheckResult::OK === $check['status']) {
                $passing[$check['id']] = $check['title'];
            }
        }

        $gaps = [];
        foreach ($ownChecks as $check) {
            if (CheckResult::OK !== $check['status'] && isset($passing[$check['id']])) {
                $gaps[] = $passing[$check['id']];
            }
        }

        return $gaps;
    }
}
