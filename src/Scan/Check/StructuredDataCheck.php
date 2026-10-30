<?php

declare(strict_types=1);

namespace App\Scan\Check;

use App\Scan\CheckResult;
use App\Scan\FetchedSite;
use App\Scan\JsonLdExtractor;
use App\Scan\ScanCheckInterface;

final readonly class StructuredDataCheck implements ScanCheckInterface
{
    public function __construct(
        private JsonLdExtractor $jsonLd,
    ) {
    }

    public function check(FetchedSite $site): CheckResult
    {
        $objects = $this->jsonLd->extract($site->homepage);
        $types = $this->jsonLd->types($objects);

        if ([] === $types) {
            return new CheckResult('structured_data', 'Structured data', CheckResult::ERROR, 'Geen JSON-LD structured data gevonden op de homepage. Dit helpt AI om je content te begrijpen.', 2);
        }

        if (null === $this->jsonLd->findByType($objects, 'Organization', 'LocalBusiness', 'Corporation')) {
            return new CheckResult('structured_data', 'Structured data', CheckResult::WARNING, 'Structured data gevonden ('.implode(', ', $types).'), maar geen Organization-schema.', 2);
        }

        return new CheckResult('structured_data', 'Structured data', CheckResult::OK, 'Gevonden: '.implode(', ', $types).'.', 2);
    }
}
