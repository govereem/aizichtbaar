<?php

declare(strict_types=1);

namespace App\Tests\Scan;

use App\Scan\CheckResult;
use App\Scan\ScoreCalculator;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    public function testAllChecksPassedGivesFullScore(): void
    {
        $results = [
            new CheckResult('a', 'A', CheckResult::OK, '', 2),
            new CheckResult('b', 'B', CheckResult::OK, '', 1),
        ];

        $this->assertSame(100, (new ScoreCalculator())->score($results));
    }

    public function testWarningCountsAsHalfAndErrorAsNothing(): void
    {
        $results = [
            new CheckResult('a', 'A', CheckResult::OK, '', 2),     // 2 of 4
            new CheckResult('b', 'B', CheckResult::WARNING, '', 2), // 1 of 4
            new CheckResult('c', 'C', CheckResult::ERROR, '', 4),   // 0 of 4
        ];

        $this->assertSame(38, (new ScoreCalculator())->score($results));
    }

    public function testSameResultsAlwaysGiveSameScore(): void
    {
        $results = [new CheckResult('a', 'A', CheckResult::WARNING, '', 3)];
        $calculator = new ScoreCalculator();

        $this->assertSame($calculator->score($results), $calculator->score($results));
    }

    public function testNoResultsGivesZero(): void
    {
        $this->assertSame(0, (new ScoreCalculator())->score([]));
    }
}
