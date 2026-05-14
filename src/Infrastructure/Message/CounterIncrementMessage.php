<?php

declare(strict_types=1);

namespace App\Infrastructure\Message;

/**
 * Message payload for asynchronous counter increment operations.
 *
 * Carries the product ID as a string to ensure serializability
 * across the message queue transport.
 */
final readonly class CounterIncrementMessage
{
    public function __construct(private string $productId)
    {
    }

    public function productId(): string
    {
        return $this->productId;
    }
}
