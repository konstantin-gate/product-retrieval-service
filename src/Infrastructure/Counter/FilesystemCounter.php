<?php

declare(strict_types=1);

namespace App\Infrastructure\Counter;

use App\Domain\Contract\CounterInterface;
use App\Domain\Contract\SyncCounterInterface;
use App\Domain\Exception\CounterException;
use App\Domain\ValueObject\ProductId;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Filesystem-based counter adapter using Symfony FilesystemAdapter.
 *
 * Stores counter values as cache items with permanent TTL.
 */
final readonly class FilesystemCounter implements CounterInterface, SyncCounterInterface
{
    private const COUNTER_KEY_PREFIX = 'counter_';

    /**
     * @param FilesystemAdapter $cache Symfony filesystem cache adapter with "counter" namespace
     */
    public function __construct(private FilesystemAdapter $cache)
    {
    }

    /**
     * Increments the view counter for a product.
     *
     * @throws CounterException if incrementing the counter fails
     */
    public function increment(ProductId $id): void
    {
        try {
            $item = $this->cache->getItem(self::COUNTER_KEY_PREFIX.$id->value());
            $current = $item->isHit() ? (int) $item->get() : 0;
            $item->set($current + 1);
            $item->expiresAfter(null);
            $this->cache->save($item);
        } catch (\Exception $e) {
            throw new CounterException('Error incrementing counter: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Returns the current view count for a product.
     *
     * @return int Current count, or 0 if the product has never been viewed
     *
     * @throws CounterException if reading the counter fails
     */
    public function getCount(ProductId $id): int
    {
        try {
            $item = $this->cache->getItem(self::COUNTER_KEY_PREFIX.$id->value());

            return $item->isHit() ? (int) $item->get() : 0;
        } catch (\Exception $e) {
            throw new CounterException('Error reading counter: '.$e->getMessage(), 0, $e);
        }
    }
}
