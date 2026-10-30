<?php

declare(strict_types=1);

namespace App\Scan;

use App\Ai\AiUnavailableException;
use App\Scan\Ai\ActionPlanWriter;
use App\Scan\Ai\AnswerSimulator;
use App\Scan\Ai\ContentSuggester;
use Psr\Log\LoggerInterface;

/**
 * The €49 scan: the basic checks on several pages, an AI answer simulation, a plan per failed check,
 * competitor gaps and ready-to-paste files.
 *
 * Deterministic parts always ship. If an AI step fails, its section is left out (or falls back to the templated
 * advice) and the result is marked degraded, so the caller can tell the customer and check the scan.
 */
final readonly class ExtendedScanRunner
{
    public function __construct(
        private SiteFetcher $fetcher,
        private SiteCrawler $crawler,
        private ScanRunner $runner,
        private AnswerSimulator $simulator,
        private ActionPlanWriter $planWriter,
        private ContentSuggester $contentSuggester,
        private CompetitorComparer $competitors,
        private ReadyToPasteContent $content,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string> $competitorUrls
     *
     * @return array<string, mixed>
     */
    public function run(string $url, array $competitorUrls): array
    {
        $site = $this->fetcher->fetch($url);
        if (null === $site->homepage) {
            throw new SiteUnreachableException();
        }

        $pages = $this->crawler->crawl($site);

        // The homepage is always first. Its evaluation is the headline score and the checks shown to the customer.
        $pageResults = [];
        foreach ($pages as $page) {
            $pageResults[] = [
                'url' => $page['url'],
                'result' => $this->runner->evaluate(new FetchedSite(
                    url: $page['url'],
                    homepage: $page['html'],
                    robotsTxt: $site->robotsTxt,
                    llmsTxt: $site->llmsTxt,
                    sitemap: $site->sitemap,
                )),
            ];
        }

        $home = $pageResults[0]['result'];
        $degradedSteps = [];

        try {
            $simulation = $this->simulator->simulate($pages);
        } catch (AiUnavailableException $e) {
            $this->logger->error('AI-antwoordsimulatie mislukt.', ['url' => $url, 'exception' => $e]);
            $simulation = null;
            $degradedSteps[] = 'simulation';
        }

        $failed = array_values(array_filter($home['checks'], fn (array $check): bool => CheckResult::OK !== $check['status']));
        $plans = [];
        try {
            $plans = $this->planWriter->write(array_map(
                fn (array $check): array => ['id' => $check['id'], 'title' => $check['title'], 'detail' => $check['detail']],
                $failed,
            ));
        } catch (AiUnavailableException $e) {
            // The templated advice from the checks still ships, so the customer gets a plan, just a less detailed one.
            $this->logger->error('Actieplan-AI mislukt.', ['url' => $url, 'exception' => $e]);
            $degradedSteps[] = 'actions';
        }

        $rewrites = null;
        try {
            $rewrites = $this->contentSuggester->suggest($pages);
        } catch (AiUnavailableException $e) {
            $this->logger->error('Voorbeeldteksten-AI mislukt.', ['url' => $url, 'exception' => $e]);
            $degradedSteps[] = 'rewrites';
        }

        $actions = array_map(
            fn (array $action): array => $action + ($plans[$action['id']] ?? ['why' => '', 'steps' => [], 'effort' => '']),
            $home['actions'],
        );

        $pageMetas = [];
        foreach ($pages as $page) {
            $pageMetas[$page['url']] = SiteMeta::fromHtml($page['html']);
        }

        $homeMeta = $pageMetas[$pages[0]['url']];

        return [
            'score' => $home['score'],
            'summary' => $home['summary'],
            'checks' => $home['checks'],
            'actions' => $actions,
            'basic_advice' => [] === $plans,
            'pages' => array_map(
                fn (array $page): array => ['url' => $page['url'], 'score' => $page['result']['score'], 'summary' => $page['result']['summary']],
                $pageResults,
            ),
            'simulation' => $simulation,
            'rewrites' => $rewrites,
            'competitors' => $this->competitors->compare($competitorUrls, $home['checks']),
            'content' => [
                'llms_txt' => $this->content->llmsTxt($url, $homeMeta, $pageMetas),
                'organization_json_ld' => $this->content->organizationJsonLd($url, $homeMeta),
                'robots_snippet' => $this->content->robotsSnippet(),
            ],
            // Names the AI steps that failed, e.g. "simulation", so the result page can say which part is missing.
            'degraded_steps' => $degradedSteps,
            'degraded' => [] !== $degradedSteps,
        ];
    }
}
