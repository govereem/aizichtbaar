<?php

declare(strict_types=1);

namespace App\Tests\Scan\Check;

use App\Scan\CheckResult;
use App\Scan\Check\AiCrawlerAccessCheck;
use App\Scan\FetchedSite;
use PHPUnit\Framework\TestCase;

class AiCrawlerAccessCheckTest extends TestCase
{
    public function testMissingRobotsTxtAllowsCrawlers(): void
    {
        $result = (new AiCrawlerAccessCheck())->check(new FetchedSite('https://voorbeeld.nl'));

        $this->assertSame(CheckResult::OK, $result->status);
    }

    public function testOneBotBlockedIsAWarningNamingThatBot(): void
    {
        $robots = "User-agent: PerplexityBot\nDisallow: /\n";

        $result = (new AiCrawlerAccessCheck())->check(new FetchedSite('https://voorbeeld.nl', robotsTxt: $robots));

        $this->assertSame(CheckResult::WARNING, $result->status);
        $this->assertStringContainsString('PerplexityBot', $result->detail);
        $this->assertStringNotContainsString('GPTBot', $result->detail);
    }

    public function testWildcardBlockBlocksEveryBotIsAnError(): void
    {
        $robots = "User-agent: *\nDisallow: /\n";

        $result = (new AiCrawlerAccessCheck())->check(new FetchedSite('https://voorbeeld.nl', robotsTxt: $robots));

        $this->assertSame(CheckResult::ERROR, $result->status);
    }

    public function testNamedGroupOverridesWildcardGroup(): void
    {
        $robots = "User-agent: GPTBot\nDisallow:\n\nUser-agent: *\nDisallow: /\n";

        $result = (new AiCrawlerAccessCheck())->check(new FetchedSite('https://voorbeeld.nl', robotsTxt: $robots));

        $this->assertStringNotContainsString('GPTBot', $result->detail);
        $this->assertStringContainsString('ClaudeBot', $result->detail);
    }

    public function testCommentsAndSharedGroupsAreRead(): void
    {
        $robots = "# AI bots\nUser-agent: GPTBot # training\nUser-agent: ClaudeBot\nDisallow: /\n";

        $result = (new AiCrawlerAccessCheck())->check(new FetchedSite('https://voorbeeld.nl', robotsTxt: $robots));

        $this->assertStringContainsString('GPTBot', $result->detail);
        $this->assertStringContainsString('ClaudeBot', $result->detail);
    }
}
