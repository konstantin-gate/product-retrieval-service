<?php

declare(strict_types=1);

namespace App\Tests\Concurrent\Infrastructure\Counter;

use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Counter\RedisCounter;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Concurrent tests verifying atomicity of RedisCounter under parallel increments.
 */
final class RedisCounterConcurrentTest extends KernelTestCase
{
    private const TEST_UUID = '550e8400-e29b-41d4-a716-446655440051';
    private const TEST_KEY = 'counter:550e8400-e29b-41d4-a716-446655440051';

    private \Redis $redis;

    protected function setUp(): void
    {
        if (!\function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl extension not available');
        }

        $redis = static::getContainer()->get(\Redis::class);
        if (!$redis instanceof \Redis) {
            throw new \RuntimeException('Redis service not found');
        }
        $this->redis = $redis;
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
                // Child process: cannot use parent's DI container, create new connection from env
                $redis = new \Redis();
                $envDsn = getenv('REDIS_URL');
                $dsn = false !== $envDsn ? $envDsn : 'redis://redis:6379/1';
                $parsed = parse_url($dsn);
                $redis->connect($parsed['host'] ?? 'redis', $parsed['port'] ?? 6379);
                $db = isset($parsed['path']) ? (int) ltrim($parsed['path'], '/') : 0;
                if ($db > 0) {
                    $redis->select($db);
                }
                $counter = new RedisCounter($redis, new NullLogger());
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

        $counter = new RedisCounter($this->redis, new NullLogger());
        self::assertSame($expectedTotal, $counter->getCount($id));
    }
}
