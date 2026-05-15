<?php

declare(strict_types=1);

namespace App\Tests\Concurrent\Infrastructure\Counter;

use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Counter\RedisCounter;
use PHPUnit\Framework\TestCase;

/**
 * Concurrent tests verifying atomicity of RedisCounter under parallel increments.
 */
final class RedisCounterConcurrentTest extends TestCase
{
    private const TEST_UUID = '550e8400-e29b-41d4-a716-446655440051';
    private const TEST_KEY = 'counter:550e8400-e29b-41d4-a716-446655440051';

    private \Redis $redis;

    protected function setUp(): void
    {
        if (!\function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl extension not available');
        }

        $this->redis = new \Redis();
        $this->redis->connect('redis', 6379);
        $this->redis->del(self::TEST_KEY);
    }

    protected function tearDown(): void
    {
        $this->redis->del(self::TEST_KEY);
    }

    public function testConcurrentIncrementsAreAtomic(): void
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
                $redis = new \Redis();
                $redis->connect('redis', 6379);
                $counter = new RedisCounter($redis);
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

        $counter = new RedisCounter($this->redis);
        self::assertSame($expectedTotal, $counter->getCount($id));
    }
}
