<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Asks a language model for an answer in JSON. Implementations throw AiUnavailableException when no usable answer comes back.
 */
interface AiClient
{
    /**
     * @return array<mixed>
     *
     * @throws AiUnavailableException
     */
    public function completeJson(string $system, string $user, int $maxTokens = 2000, string $purpose = 'unknown'): array;
}
