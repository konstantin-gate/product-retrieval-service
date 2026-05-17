<?php

declare(strict_types=1);

namespace App\Infrastructure\Counter;

use App\Domain\Contract\CounterInterface;
use App\Domain\Contract\SyncCounterInterface;
use App\Domain\Exception\CounterException;
use App\Domain\ValueObject\ProductId;
use Psr\Log\LoggerInterface;

/**
 * Redis-based counter adapter using the Redis INCR command.
 */
final readonly class RedisCounter implements CounterInterface, SyncCounterInterface
{
    private const COUNTER_KEY_PREFIX = 'counter:';

    /**
     * @param \Redis          $redis  Pre-configured Redis connection
     * @param LoggerInterface $logger Logger for counter operation errors
     */
    public function __construct(
        private \Redis $redis,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Increments the view counter for a product using Redis INCR.
     *
     * @throws CounterException if the Redis operation fails
     */
    public function increment(ProductId $id): void
    {
        try {
            $this->redis->incr(self::COUNTER_KEY_PREFIX.$id->value());
        } catch (\Exception $e) {
            $this->logger->error('Counter increment failed', [
                'productId' => $id->value(),
                'driver' => 'redis',
                'exception' => $e->getMessage(),
            ]);
            throw new CounterException('Error incrementing in Redis: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Returns the current view count for a product.
     *
     * @return int Current count, or 0 if the product has never been viewed
     *
     * @throws CounterException if the Redis operation fails
     */
    public function getCount(ProductId $id): int
    {
        try {
            $value = $this->redis->get(self::COUNTER_KEY_PREFIX.$id->value());

            return false !== $value ? (int) $value : 0;
        } catch (\Exception $e) {
            $this->logger->error('Counter read failed', [
                'productId' => $id->value(),
                'driver' => 'redis',
                'exception' => $e->getMessage(),
            ]);
            throw new CounterException('Error reading from Redis: '.$e->getMessage(), 0, $e);
        }
    }
}
