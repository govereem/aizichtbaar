<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\AiResponseStore;
use App\Ai\AiUnavailableException;
use App\Ai\ClaudeClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ClaudeClientTest extends TestCase
{
    public function testMissingApiKeySkipsTheCallEntirely(): void
    {
        $client = new ClaudeClient(new MockHttpClient(function () {
            $this->fail('No request may be sent without an API key.');
        }), new NullLogger(), null, 'claude-sonnet-5-5');

        $this->expectException(AiUnavailableException::class);

        $client->completeJson('system', 'user');
    }

    public function testSendsKeyAndModelAndDecodesFencedJson(): void
    {
        $seen = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen) {
            $seen = ['method' => $method, 'url' => $url, 'headers' => $options['headers'], 'body' => json_decode($options['body'], true)];

            return new MockResponse(json_encode(['content' => [['type' => 'text', 'text' => "```json\n{\"niche\": \"tandarts\"}\n```"]]]));
        });

        $result = (new ClaudeClient($http, new NullLogger(), 'test-key', 'claude-sonnet-5-5'))->completeJson('system', 'user', 500);

        $this->assertSame(['niche' => 'tandarts'], $result);
        $this->assertSame('POST', $seen['method']);
        $this->assertSame('https://api.anthropic.com/v1/messages', $seen['url']);
        $this->assertContains('x-api-key: test-key', $seen['headers']);
        $this->assertSame('claude-sonnet-5-5', $seen['body']['model']);
        $this->assertSame(500, $seen['body']['max_tokens']);
    }

    public function testErrorStatusBecomesUnavailable(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error": "overloaded"}', ['http_code' => 529]));

        $this->expectException(AiUnavailableException::class);

        (new ClaudeClient($http, new NullLogger(), 'test-key', 'claude-sonnet-5-5'))->completeJson('system', 'user');
    }

    public function testNonJsonAnswerBecomesUnavailable(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['content' => [['type' => 'text', 'text' => 'Sorry, geen JSON.']]])));

        $this->expectException(AiUnavailableException::class);

        (new ClaudeClient($http, new NullLogger(), 'test-key', 'claude-sonnet-5-5'))->completeJson('system', 'user');
    }

    public function testEveryOutcomeIsStoredWithItsPurpose(): void
    {
        $directory = sys_get_temp_dir().'/aiz-client-'.bin2hex(random_bytes(4));
        $store = new AiResponseStore($directory, new NullLogger(), new Filesystem());
        $http = new MockHttpClient([
            new MockResponse(json_encode(['content' => [['type' => 'text', 'text' => '{"niche": "tandarts"}']]])),
            new MockResponse('{"error": "overloaded"}', ['http_code' => 529]),
            new MockResponse(json_encode(['content' => [['type' => 'text', 'text' => 'Sorry, geen JSON.']]])),
        ]);
        $client = new ClaudeClient($http, new NullLogger(), 'test-key', 'claude-sonnet-5-5', $store);

        $client->completeJson('system', 'user', 500, 'simulation');
        try {
            $client->completeJson('system', 'user', 500, 'actions');
        } catch (AiUnavailableException) {
        }
        try {
            $client->completeJson('system', 'user', 500, 'rewrites');
        } catch (AiUnavailableException) {
        }

        $outcomes = [];
        foreach (glob($directory.'/*.json') ?: [] as $file) {
            $record = json_decode((string) file_get_contents($file), true);
            $outcomes[$record['purpose']] = [$record['outcome'], $record['status']];
        }
        (new Filesystem())->remove($directory);

        $this->assertEqualsCanonicalizing([
            'simulation' => ['ok', 200],
            'actions' => ['api_error', 529],
            'rewrites' => ['invalid_json', 200],
        ], $outcomes);
    }
}
