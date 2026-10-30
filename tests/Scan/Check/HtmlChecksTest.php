<?php

declare(strict_types=1);

namespace App\Tests\Scan\Check;

use App\Scan\CheckResult;
use App\Scan\Check\ContentStructureCheck;
use App\Scan\Check\EntityBrandCheck;
use App\Scan\Check\RenderabilityCheck;
use App\Scan\Check\StructuredDataCheck;
use App\Scan\FetchedSite;
use App\Scan\JsonLdExtractor;
use PHPUnit\Framework\TestCase;

class HtmlChecksTest extends TestCase
{
    private const ORGANIZATION_WITH_SAME_AS = <<<'HTML'
        <html><head>
        <script type="application/ld+json">
        {"@context": "https://schema.org", "@graph": [
            {"@type": "WebSite", "name": "Voorbeeld"},
            {"@type": "Organization", "name": "Voorbeeld BV", "sameAs": ["https://www.linkedin.com/company/voorbeeld"]}
        ]}
        </script>
        </head><body><h1>Voorbeeld</h1></body></html>
        HTML;

    public function testStructuredDataWithoutJsonLdIsAnError(): void
    {
        $result = (new StructuredDataCheck(new JsonLdExtractor()))->check(new FetchedSite('https://voorbeeld.nl', homepage: '<html></html>'));

        $this->assertSame(CheckResult::ERROR, $result->status);
    }

    public function testStructuredDataReadsObjectsInsideGraph(): void
    {
        $result = (new StructuredDataCheck(new JsonLdExtractor()))->check(new FetchedSite('https://voorbeeld.nl', homepage: self::ORGANIZATION_WITH_SAME_AS));

        $this->assertSame(CheckResult::OK, $result->status);
        $this->assertStringContainsString('Organization', $result->detail);
    }

    public function testOrganizationWithoutSameAsIsAWarning(): void
    {
        $html = '<script type="application/ld+json">{"@type": "Organization", "name": "Voorbeeld"}</script>';

        $result = (new EntityBrandCheck(new JsonLdExtractor()))->check(new FetchedSite('https://voorbeeld.nl', homepage: $html));

        $this->assertSame(CheckResult::WARNING, $result->status);
    }

    public function testOrganizationWithSameAsPasses(): void
    {
        $result = (new EntityBrandCheck(new JsonLdExtractor()))->check(new FetchedSite('https://voorbeeld.nl', homepage: self::ORGANIZATION_WITH_SAME_AS));

        $this->assertSame(CheckResult::OK, $result->status);
    }

    public function testJavaScriptOnlyPageIsNotRenderable(): void
    {
        $html = '<html><body><div id="root"></div><script>document.body.innerHTML = "veel tekst";</script></body></html>';

        $result = (new RenderabilityCheck())->check(new FetchedSite('https://voorbeeld.nl', homepage: $html));

        $this->assertSame(CheckResult::ERROR, $result->status);
    }

    public function testContentWithStructureAndAnswerFirstPasses(): void
    {
        $answer = str_repeat('Dit is een kort en direct antwoord op de vraag van de klant. ', 2);
        $html = sprintf(
            '<html><body><h1>Titel</h1><h2>Wat doen wij?</h2><p>%s</p><h2>Waar zijn jullie?</h2><p>%s</p></body></html>',
            $answer,
            $answer,
        );

        $result = (new ContentStructureCheck())->check(new FetchedSite('https://voorbeeld.nl', homepage: $html));

        $this->assertSame(CheckResult::OK, $result->status);
    }

    public function testContentWithoutHeadingsIsAnError(): void
    {
        $result = (new ContentStructureCheck())->check(new FetchedSite('https://voorbeeld.nl', homepage: '<html><body><p>Tekst</p></body></html>'));

        $this->assertSame(CheckResult::ERROR, $result->status);
    }
}
