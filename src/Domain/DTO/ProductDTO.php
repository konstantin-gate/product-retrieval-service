<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use App\Domain\ValueObject\Price;
use App\Domain\ValueObject\ProductId;

/**
 * Immutable data transfer object carrying product data between layers.
 *
 * Unlike Value Objects, DTOs do not enforce domain invariants —
 * they are simple data carriers with constructor property promotion.
 */
final readonly class ProductDTO
{
    /**
     * @param ProductId $id          Unique product identifier
     * @param string    $name        Product display name
     * @param Price     $price       Product price in CZK
     * @param string    $description Product description text
     */
    public function __construct(
        public ProductId $id,
        public string $name,
        public Price $price,
        public string $description,
    ) {
    }

    /**
     * Creates a ProductDTO from a raw data array.
     *
     * @param array<string, mixed> $data Raw product data with keys: id, name, price, description
     *
     * @throws \InvalidArgumentException if required keys are missing
     */
    public static function fromArray(array $data): self
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

        return new self(
            ProductId::fromString((string) $data['id']),
            (string) $data['name'],
            Price::of((string) $data['price']),
            (string) $data['description'],
        );
    }
}
