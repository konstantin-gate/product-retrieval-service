<?php

declare(strict_types=1);

namespace App\Infrastructure\Counter;

use App\Domain\Contract\CounterInterface;
use App\Domain\Contract\SyncCounterInterface;
use App\Domain\ValueObject\ProductId;

/**
 * No-op counter adapter that discards all increments and always returns 0.
 *
 * Used when counting is disabled (ACTIVE_COUNTER_MODE=null).
 */
final readonly class NullCounter implements CounterInterface, SyncCounterInterface
{
    public function increment(ProductId $id): void
    {
    }

    public function getCount(ProductId $id): int
    {
        return 0;
    }
}
