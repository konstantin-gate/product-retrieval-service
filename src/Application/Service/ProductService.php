<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Contract\CacheInterface;
use App\Domain\Contract\CounterInterface;
use App\Domain\Contract\ProductSourceInterface;
use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\ProductId;

/**
 * Orchestrates product-related use cases.
 *
 * Coordinates counter increments, cache lookups, and data retrieval
 * from primary sources.
 */
final readonly class ProductService
{
    /**
     * @param ProductSourceInterface $source  Primary data source (MySQL/ES)
     * @param CacheInterface         $cache   Cache adapter
     * @param CounterInterface       $counter Counter adapter
     */
    public function __construct(
        private ProductSourceInterface $source,
        private CacheInterface $cache,
        private CounterInterface $counter,
    ) {
    }

    /**
     * Retrieves a product by its ID, with caching and view counting.
     *
     * Workflow: increment counter -> check cache -> fetch from source -> update cache.
     *
     * @param ProductId $id Product identifier
     *
     * @return ProductDTO Full product data
     */
    public function getProduct(ProductId $id): ProductDTO
    {
        $this->counter->increment($id);

        $cacheKey = 'product_'.$id->value();
        $cached = $this->cache->get($cacheKey);

        if (null !== $cached) {
            return $cached;
        }

        $product = $this->source->findById($id);

        $this->cache->set($cacheKey, $product);

        return $product;
    }
}
