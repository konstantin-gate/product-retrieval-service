<?php

declare(strict_types=1);

namespace App\Domain\Contract;

/**
 * Marker interface for synchronous counter implementations.
 *
 * Used to distinguish bare sync counters from async-decorated ones
 * in the DI container. This prevents recursive message dispatch
 * when the CounterIncrementHandler processes async queue messages.
 */
interface SyncCounterInterface extends CounterInterface
{
}
