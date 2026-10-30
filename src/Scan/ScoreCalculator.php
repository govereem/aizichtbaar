<?php

declare(strict_types=1);

namespace App\Scan;

final class ScoreCalculator
{
    private const FACTORS = [
        CheckResult::OK => 1.0,
        CheckResult::WARNING => 0.5,
        CheckResult::ERROR => 0.0,
    ];

    /**
     * Weighted average of the check results, 0-100. The same results always give the same score.
     *
     * @param list<CheckResult> $results
     */
    public function score(array $results): int
    {
        $totalWeight = array_sum(array_map(fn (CheckResult $result): int => $result->weight, $results));
        if (0 === $totalWeight) {
            return 0;
        }

        $earned = array_sum(array_map(
            fn (CheckResult $result): float => $result->weight * self::FACTORS[$result->status],
            $results,
        ));

        return (int) round($earned / $totalWeight * 100);
    }
}
