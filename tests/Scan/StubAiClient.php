<?php

declare(strict_types=1);

namespace App\Tests\Scan;

use App\Ai\AiClient;
use App\Ai\AiUnavailableException;

/**
 * Returns queued answers in order. An exception in the queue is thrown instead, and an empty queue means the AI is unavailable.
 */
final class StubAiClient implements AiClient
{
    /** @var list<array<mixed>|\Throwable> */
    private array $queue;

    /** The user message of the last call, so tests can check what the model was given. */
    public string $lastUserMessage = '';

    /**
     * @param list<array<mixed>|\Throwable> $queue
     */
    public function __construct(array $queue = [])
    {
        $this->queue = $queue;
    }

    public function completeJson(string $system, string $user, int $maxTokens = 2000, string $purpose = 'unknown'): array
    {
        $this->lastUserMessage = $user;

        $next = array_shift($this->queue);

        if (null === $next) {
            throw new AiUnavailableException('Geen antwoord in de stub.');
        }

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
