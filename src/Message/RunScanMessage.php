<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Asks a worker to run the checks for one scan. Only the id travels, so the handler reads the current scan row.
 */
final readonly class RunScanMessage
{
    public function __construct(
        public int $scanId,
    ) {
    }
}
