<?php

declare(strict_types=1);

namespace App\Scan\Check;

use App\Scan\CheckResult;
use App\Scan\FetchedSite;
use App\Scan\ScanCheckInterface;

final readonly class AiCrawlerAccessCheck implements ScanCheckInterface
{
    private const BOTS = ['GPTBot', 'ChatGPT-User', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'CCBot'];

    public function check(FetchedSite $site): CheckResult
    {
        if (null === $site->robotsTxt) {
            return new CheckResult('ai_crawlers', 'AI-crawlers', CheckResult::OK, 'Geen robots.txt gevonden, dus AI-crawlers mogen de site standaard crawlen.', 3);
        }

        $blocked = $this->blockedBots($site->robotsTxt);
        if ([] === $blocked) {
            return new CheckResult('ai_crawlers', 'AI-crawlers', CheckResult::OK, 'Alle onderzochte AI-crawlers mogen de site crawlen.', 3);
        }

        $status = count($blocked) === count(self::BOTS) ? CheckResult::ERROR : CheckResult::WARNING;

        return new CheckResult('ai_crawlers', 'AI-crawlers', $status, 'Geblokkeerd in robots.txt voor: '.implode(', ', $blocked).'.', 3);
    }

    /**
     * @return list<string>
     */
    private function blockedBots(string $robotsTxt): array
    {
        $groups = $this->parseGroups($robotsTxt);

        $blocked = [];
        foreach (self::BOTS as $bot) {
            // A group naming the bot wins over the wildcard group, as in the robots exclusion standard.
            $rules = $groups[strtolower($bot)] ?? $groups['*'] ?? [];
            if (in_array('/', $rules, true)) {
                $blocked[] = $bot;
            }
        }

        return $blocked;
    }

    /**
     * @return array<string, list<string>> lowercase user agent => disallowed paths
     */
    private function parseGroups(string $robotsTxt): array
    {
        $groups = [];
        $current = [];
        $expectingAgents = true;

        foreach (preg_split('/\R/', $robotsTxt) as $line) {
            $line = trim((string) preg_replace('/#.*/', '', $line));
            if (!str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ('user-agent' === $field) {
                if (!$expectingAgents) {
                    $current = [];
                }
                $agent = strtolower($value);
                $current[] = $agent;
                $groups[$agent] ??= [];
                $expectingAgents = true;

                continue;
            }

            $expectingAgents = false;
            if ('disallow' === $field) {
                foreach ($current as $agent) {
                    $groups[$agent][] = $value;
                }
            }
        }

        return $groups;
    }
}
