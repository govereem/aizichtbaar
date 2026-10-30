<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * The AI step could not produce a usable answer. The scan still ships its deterministic parts.
 */
final class AiUnavailableException extends \RuntimeException
{
}
