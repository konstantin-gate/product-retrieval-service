<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\ValueObject\ProductId;

/**
 * Contract for product view counter operations.
 *
 * Implementations may be file-based, Redis-based, or no-op.
 */
interface CounterInterface
{
    /**
     * Increments the view counter for a product.
     */
    public function increment(ProductId $id): void;

    /**
     * Returns the current view count for a product.
     *
     * @return int Current count, or 0 if never viewed
     */
    public function getCount(ProductId $id): int;
}
