<?php

declare(strict_types=1);

namespace App\Ai;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ClaudeClient implements AiClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private HttpClientInterface $http,
        private LoggerInterface $logger,
        #[Autowire('%anthropic.api_key%')]
        private ?string $apiKey,
        #[Autowire('%anthropic.model%')]
        private string $model,
        private ?AiResponseStore $store = null,
    ) {
    }

    public function completeJson(string $system, string $user, int $maxTokens = 2000, string $purpose = 'unknown'): array
    {
        // Without a key the AI steps are skipped, so tests and local environments never reach the network.
        if (null === $this->apiKey || '' === $this->apiKey) {
            throw new AiUnavailableException('ANTHROPIC_API_KEY is niet ingesteld.');
        }

        try {
            $response = $this->http->request('POST', self::ENDPOINT, [
                'timeout' => 60,
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                ],
            ]);

            $status = $response->getStatusCode();
            $raw = $response->getContent(false);
        } catch (ExceptionInterface $e) {
            throw new AiUnavailableException('Claude API niet bereikbaar.', 0, $e);
        }

        if (200 !== $status) {
            // The error body says why (invalid key, unknown model, overload). Log it so the cause is visible.
            $this->logger->error('Claude API antwoordde met een foutstatus.', [
                'status' => $status,
                'body' => mb_substr($raw, 0, 300),
            ]);
            $this->record($purpose, $system, $user, $maxTokens, $status, 'api_error', $raw);

            throw new AiUnavailableException(sprintf('Claude API antwoordde met status %d.', $status));
        }

        $body = json_decode($raw, true);
        $text = (string) ($body['content'][0]['text'] ?? '');

        $data = $this->decode($text);
        if (null === $data) {
            $this->logger->warning('Claude gaf geen geldige JSON terug.', ['excerpt' => mb_substr($text, 0, 300)]);
            $this->record($purpose, $system, $user, $maxTokens, $status, 'invalid_json', $raw);

            throw new AiUnavailableException('Ongeldige JSON van Claude.');
        }

        $this->record($purpose, $system, $user, $maxTokens, $status, 'ok', $raw);

        return $data;
    }

    /**
     * Models sometimes wrap JSON in a markdown fence. Strip that before decoding.
     *
     * @return array<mixed>|null
     */
    private function decode(string $text): ?array
    {
        $json = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text)));
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    private function record(string $purpose, string $system, string $user, int $maxTokens, int $status, string $outcome, string $raw): void
    {
        $this->store?->store($purpose, $this->model, $maxTokens, $system, $user, $status, $outcome, $raw);
    }
}
