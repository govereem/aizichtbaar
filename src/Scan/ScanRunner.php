<?php

declare(strict_types=1);

namespace App\Scan;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ScanRunner
{
    public function __construct(
        private SiteFetcher $fetcher,
        #[AutowireIterator('app.scan_check')]
        private iterable $checks,
        private ScoreCalculator $scorer,
        private ReadyToPasteContent $content,
    ) {
    }

    /**
     * @return array{score: int, summary: string, checks: list<array<string, mixed>>, actions: list<array<string, string>>, basic_advice: bool, content: array{llms_txt: string, organization_json_ld: string, robots_snippet: string}}
     */
    public function run(string $url): array
    {
        $site = $this->fetcher->fetch($url);

        return $this->evaluate($site) + ['content' => $this->readyToPaste($site)];
    }

    /**
     * Runs the checks on a site that was already fetched.
     *
     * @return array{score: int, summary: string, checks: list<array<string, mixed>>, actions: list<array<string, string>>, basic_advice: bool}
     */
    public function evaluate(FetchedSite $site): array
    {
        if (null === $site->homepage) {
            throw new SiteUnreachableException();
        }

        $results = [];
        foreach ($this->checks as $check) {
            $results[] = $check->check($site);
        }

        $passed = count(array_filter($results, fn (CheckResult $result): bool => CheckResult::OK === $result->status));

        return [
            'score' => $this->scorer->score($results),
            'summary' => sprintf('%d van %d controles geslaagd.', $passed, count($results)),
            'checks' => array_map(fn (CheckResult $result): array => $result->toArray(), $results),
            'actions' => $this->actions($results),
            // Templated from the checks. AI-written advice replaces this in a later step.
            'basic_advice' => true,
        ];
    }

    /**
     * The files for the free result are built from the homepage alone. The paid scan builds them from all crawled pages.
     *
     * @return array{llms_txt: string, organization_json_ld: string, robots_snippet: string}
     */
    private function readyToPaste(FetchedSite $site): array
    {
        $meta = SiteMeta::fromHtml($site->homepage);

        return [
            'llms_txt' => $this->content->llmsTxt($site->url, $meta, [$site->url => $meta]),
            'organization_json_ld' => $this->content->organizationJsonLd($site->url, $meta),
            'robots_snippet' => $this->content->robotsSnippet(),
        ];
    }

    /**
     * Every check that is not OK becomes an action, weighted by how much it counts.
     *
     * @param list<CheckResult> $results
     *
     * @return list<array{id: string, title: string, text: string, impact: string}>
     */
    private function actions(array $results): array
    {
        $actions = [];
        foreach ($results as $result) {
            if (CheckResult::OK === $result->status) {
                continue;
            }

            $actions[] = [
                'id' => $result->id,
                'title' => $result->title,
                'text' => $result->detail,
                'impact' => $result->weight >= 2 ? 'Hoog' : 'Medium',
            ];
        }

        return $actions;
    }
}
