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
}
