<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Contract\IMysqlDriver;
use App\Domain\Contract\ProductSourceInterface;
use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Factory\ProductDTOFactory;

/**
 * MySQL-based product source adapter.
 *
 * Delegates product retrieval to the IMysqlDriver port.
 */
final readonly class MySqlProductAdapter implements ProductSourceInterface
{
    /**
     * @param IMysqlDriver $driver MySQL driver implementation
     */
    public function __construct(private IMysqlDriver $driver)
    {
    }

    public function findById(ProductId $id): ProductDTO
    {
        $data = $this->driver->findProduct($id->value());

        return ProductDTOFactory::fromArray($data);
    }

    public function findSampleIds(int $limit): array
    {
        return $this->driver->findAllIds($limit);
    }
}
