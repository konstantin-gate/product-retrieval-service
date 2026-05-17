<?php

declare(strict_types=1);

namespace App\Tests\Concurrent\Infrastructure\Counter;

use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Counter\FilesystemCounter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Concurrent tests demonstrating race condition in FilesystemCounter.
 *
 * Because FilesystemCounter uses read-modify-write via Symfony FilesystemAdapter,
 * parallel increments from multiple processes are expected to lose updates.
 */
final class FilesystemCounterConcurrentTest extends TestCase
{
    private const TEST_UUID = '550e8400-e29b-41d4-a716-446655440060';

    private string $tempDir;

    protected function setUp(): void
    {
        if (!\function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl extension not available');
        }

        $this->tempDir = \sys_get_temp_dir().'/logio_fs_counter_test_'.\uniqid();
        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDir) && \is_dir($this->tempDir)) {
            (new Filesystem())->remove($this->tempDir);
        }
    }

    public function testConcurrentIncrementsLoseUpdates(): void
    {
        $id = ProductId::fromString(self::TEST_UUID);

        $processCount = 10;
        $incrementsPerProcess = 10;
        $expectedTotal = $processCount * $incrementsPerProcess;
        $children = [];

        for ($i = 0; $i < $processCount; ++$i) {
            $pid = \pcntl_fork();
            if (-1 === $pid) {
                self::fail('Could not fork');
            }
            if (0 === $pid) {
                $adapter = new FilesystemAdapter('counter', 0, $this->tempDir);
                $counter = new FilesystemCounter($adapter, new NullLogger());
                for ($j = 0; $j < $incrementsPerProcess; ++$j) {
                    $counter->increment($id);
                }
                exit(0);
            }
            $children[] = $pid;
        }

        foreach ($children as $pid) {
            \pcntl_waitpid($pid, $status);
        }

        $adapter = new FilesystemAdapter('counter', 0, $this->tempDir);
        $counter = new FilesystemCounter($adapter, new NullLogger());

        $actualCount = $counter->getCount($id);

        // The race condition causes lost increments; actual count must be < 100.
        self::assertLessThan($expectedTotal, $actualCount);
    }
}
