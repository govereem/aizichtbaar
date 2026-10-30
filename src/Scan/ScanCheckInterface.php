<?php

declare(strict_types=1);

namespace App\Scan;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Each implementation is one check on a fetched site. Checks are collected by tag, so adding one needs no wiring.
 */
#[AutoconfigureTag('app.scan_check')]
interface ScanCheckInterface
{
    public function check(FetchedSite $site): CheckResult;
}
