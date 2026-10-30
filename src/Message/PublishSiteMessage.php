<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Asks a worker to decide whether a completed anonymous scan gets a public page. Only the id travels, like RunScanMessage.
 */
final readonly class PublishSiteMessage
{
    public function __construct(
        public int $scanId,
    ) {
    }
}
