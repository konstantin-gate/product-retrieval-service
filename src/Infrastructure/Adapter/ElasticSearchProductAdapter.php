<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Contract\IElasticSearchDriver;
use App\Domain\Contract\ProductSourceInterface;
use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\ProductId;

/**
 * ElasticSearch-based product source adapter.
 *
 * Delegates product retrieval to the IElasticSearchDriver port.
 */
final readonly class ElasticSearchProductAdapter implements ProductSourceInterface
{
    /**
     * @param IElasticSearchDriver $driver ElasticSearch driver implementation
     */
    public function __construct(private IElasticSearchDriver $driver)
    {
    }

    public function findById(ProductId $id): ProductDTO
    {
        $data = $this->driver->findById($id->value());

        return ProductDTO::fromArray($data);
    }
}
