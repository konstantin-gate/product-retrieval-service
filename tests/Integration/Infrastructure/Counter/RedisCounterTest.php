<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Counter;

use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Counter\RedisCounter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for RedisCounter with real Redis (Docker).
 */
final class RedisCounterTest extends TestCase
{
    private \Redis $redis;
    private RedisCounter $counter;

    private const TEST_ID_1 = '550e8400-e29b-41d4-a716-446655440041';
    private const TEST_ID_2 = '550e8400-e29b-41d4-a716-446655440042';

    protected function setUp(): void
    {
        $this->redis = new \Redis();
        $this->redis->connect('redis', 6379);
        $this->counter = new RedisCounter($this->redis);

        // Clean up test keys
        $this->redis->del('counter:'.self::TEST_ID_1);
        $this->redis->del('counter:'.self::TEST_ID_2);
    }

    protected function tearDown(): void
    {
        $this->redis->del('counter:'.self::TEST_ID_1);
        $this->redis->del('counter:'.self::TEST_ID_2);
    }

    public function testIncrementFromZero(): void
    {
        $id = ProductId::fromString(self::TEST_ID_1);

        $this->counter->increment($id);

        self::assertSame(1, $this->counter->getCount($id));
    }

    public function testMultipleIncrementsAccumulate(): void
    {
        $id = ProductId::fromString(self::TEST_ID_1);

        for ($i = 0; $i < 5; ++$i) {
            $this->counter->increment($id);
        }

        self::assertSame(5, $this->counter->getCount($id));
    }

    public function testGetCountReturnsZeroForNeverIncrementedProduct(): void
    {
        $id = ProductId::fromString(self::TEST_ID_2);

        self::assertSame(0, $this->counter->getCount($id));
    }

    public function testDifferentProductsHaveIndependentCounters(): void
    {
        $id1 = ProductId::fromString(self::TEST_ID_1);
        $id2 = ProductId::fromString(self::TEST_ID_2);

        for ($i = 0; $i < 3; ++$i) {
            $this->counter->increment($id1);
        }
        for ($i = 0; $i < 7; ++$i) {
            $this->counter->increment($id2);
        }

        self::assertSame(3, $this->counter->getCount($id1));
        self::assertSame(7, $this->counter->getCount($id2));
    }
}
