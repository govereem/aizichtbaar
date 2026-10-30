<?php

declare(strict_types=1);

namespace App\Scan;

/**
 * The homepage could not be fetched. Without it no check means anything, so the scan fails instead of scoring.
 */
final class SiteUnreachableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('De homepage van deze site kon niet worden opgehaald. Controleer of de site online is en of de URL klopt.');
    }
}
