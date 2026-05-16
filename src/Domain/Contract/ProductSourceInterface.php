<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\ProductId;

/**
 * Contract for retrieving product data from a storage backend.
 *
 * Implementations may use MySQL, ElasticSearch, or other sources.
 */
interface ProductSourceInterface extends SampleIdProviderInterface
{
    /**
     * Finds a product by its unique ID.
     *
     * @param ProductId $id Product identifier
     *
     * @return ProductDTO Full product data
     *
     * @throws \RuntimeException if the product is not found or source is unavailable
     */
    public function findById(ProductId $id): ProductDTO;
}
