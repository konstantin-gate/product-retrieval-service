<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\Price;
use App\Domain\ValueObject\ProductId;

/**
 * Creates ProductDTO instances from raw data arrays returned by drivers.
 * Lives in Infrastructure because it knows the shape of raw driver output.
 */
final readonly class ProductDTOFactory
{
    /**
     * Creates a ProductDTO from a raw data array.
     *
     * @param array<string, mixed> $data Raw product data with keys: id, name, price, description
     *
     * @throws \InvalidArgumentException if required keys are missing
     */
    public static function fromArray(array $data): ProductDTO
    {
        if (!\array_key_exists('id', $data)) {
            throw new \InvalidArgumentException('Missing key: id');
        }
        if (!\array_key_exists('name', $data)) {
            throw new \InvalidArgumentException('Missing key: name');
        }
        if (!\array_key_exists('price', $data)) {
            throw new \InvalidArgumentException('Missing key: price');
        }
        if (!\array_key_exists('description', $data)) {
            throw new \InvalidArgumentException('Missing key: description');
        }

        $id = $data['id'];
        $name = $data['name'];
        $price = $data['price'];
        $description = $data['description'];

        if (!\is_scalar($id) || !\is_scalar($name) || !\is_scalar($price) || !\is_scalar($description)) {
            throw new \InvalidArgumentException('Product data contains non-scalar values');
        }

        return new ProductDTO(
            ProductId::fromString((string) $id),
            (string) $name,
            Price::of((string) $price),
            (string) $description,
        );
    }
}
