<?php

declare(strict_types=1);

namespace App\Ai;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * Keeps the raw prompt and answer of every Claude call, so failed or odd answers can be debugged later.
 */
final readonly class AiResponseStore
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/ai/response')]
        private string $directory,
        private LoggerInterface $logger,
        private Filesystem $filesystem,
    ) {
    }

    public function store(string $purpose, string $model, int $maxTokens, string $system, string $user, ?int $status, string $outcome, string $response): void
    {
        $name = sprintf('%s-%s-%s.json', (new \DateTimeImmutable())->format('Ymd-His-v'), $purpose, bin2hex(random_bytes(4)));

        $record = [
            'purpose' => $purpose,
            'model' => $model,
            'max_tokens' => $maxTokens,
            'status' => $status,
            'outcome' => $outcome,
            'system' => $system,
            'user' => $user,
            'response' => $response,
        ];

        try {
            $this->filesystem->dumpFile(
                $this->directory.'/'.$name,
                json_encode($record, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR),
            );
        } catch (IOExceptionInterface|\JsonException $e) {
            // Debug storage must never break a scan, so a failed write is only logged.
            $this->logger->warning('AI-antwoord kon niet worden opgeslagen.', ['purpose' => $purpose, 'exception' => $e]);
        }
    }
}
