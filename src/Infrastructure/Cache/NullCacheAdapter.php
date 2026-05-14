<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Contract\CacheInterface;
use App\Domain\DTO\ProductDTO;

/**
 * No-op cache adapter that always returns cache misses.
 *
 * Used when caching is disabled (ACTIVE_CACHE_DRIVER=null).
 */
final readonly class NullCacheAdapter implements CacheInterface
{
    public function get(string $key): ?ProductDTO
    {
        return null;
    }

    public function set(string $key, ProductDTO $value, ?int $ttl = null): void
    {
    }

    public function delete(string $key): void
    {
    }
}
