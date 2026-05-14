<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\DTO\ProductDTO;

/**
 * Contract for caching ProductDTO objects.
 *
 * Implementations may be file-based, Redis-based, or no-op.
 */
interface CacheInterface
{
    /**
     * Retrieves a cached product by key.
     *
     * @return ProductDTO|null Cached product, or null on cache miss
     */
    public function get(string $key): ?ProductDTO;

    /**
     * Stores a product in the cache.
     *
     * @param string     $key   Cache key
     * @param ProductDTO $value Product to cache
     * @param int|null   $ttl   Time-to-live in seconds, or null for permanent cache
     */
    public function set(string $key, ProductDTO $value, ?int $ttl = null): void;

    /**
     * Removes a cached product by key.
     */
    public function delete(string $key): void;
}
