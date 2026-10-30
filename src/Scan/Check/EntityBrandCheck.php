<?php

declare(strict_types=1);

namespace App\Scan\Check;

use App\Scan\CheckResult;
use App\Scan\FetchedSite;
use App\Scan\JsonLdExtractor;
use App\Scan\ScanCheckInterface;

final readonly class EntityBrandCheck implements ScanCheckInterface
{
    public function __construct(
        private JsonLdExtractor $jsonLd,
    ) {
    }

    public function check(FetchedSite $site): CheckResult
    {
        $objects = $this->jsonLd->extract($site->homepage);
        $organization = $this->jsonLd->findByType($objects, 'Organization', 'LocalBusiness', 'Corporation');

        if (null === $organization) {
            return new CheckResult('entity', 'Entiteit & merk', CheckResult::ERROR, 'Geen Organization-schema gevonden. AI-systemen weten dan niet zeker wie je bent.', 2);
        }

        if (empty($organization['sameAs'])) {
            return new CheckResult('entity', 'Entiteit & merk', CheckResult::WARNING, 'Organization-schema zonder sameAs-links. Koppel je site aan je profielen, zoals LinkedIn, KvK en Google Bedrijfsprofiel.', 2);
        }

        return new CheckResult('entity', 'Entiteit & merk', CheckResult::OK, 'Organization-schema met sameAs-links naar externe profielen.', 2);
    }
}
