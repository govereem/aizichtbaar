<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\AiResponseStore;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class AiResponseStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/aiz-store-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testStoresTheFullRecordAsJson(): void
    {
        $store = new AiResponseStore($this->directory, new NullLogger(), new Filesystem());

        $store->store('rewrites', 'claude-sonnet-5-5', 1500, 'system text', 'user text', 200, 'ok', '{"content":[]}');

        $files = glob($this->directory.'/*-rewrites-*.json');
        $this->assertCount(1, $files);

        $record = json_decode((string) file_get_contents($files[0]), true);
        $this->assertSame([
            'purpose' => 'rewrites',
            'model' => 'claude-sonnet-5-5',
            'max_tokens' => 1500,
            'status' => 200,
            'outcome' => 'ok',
            'system' => 'system text',
            'user' => 'user text',
            'response' => '{"content":[]}',
        ], $record);
    }

    public function testAFailedWriteIsLoggedAndDoesNotThrow(): void
    {
        // A regular file where the directory should be makes the write fail.
        file_put_contents($this->directory, 'not a directory');
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        (new AiResponseStore($this->directory, $logger, new Filesystem()))->store('actions', 'claude-sonnet-5-5', 3000, 'system', 'user', 500, 'api_error', 'boom');

        $this->addToAssertionCount(1);
    }
}
