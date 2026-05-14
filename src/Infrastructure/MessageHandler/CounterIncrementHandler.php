<?php

declare(strict_types=1);

namespace App\Infrastructure\MessageHandler;

use App\Domain\Contract\SyncCounterInterface;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Message\CounterIncrementMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles asynchronous counter increment messages from the Messenger queue.
 *
 * Uses a SyncCounterInterface to avoid recursive dispatch when the
 * active counter mode is "async".
 */
final readonly class CounterIncrementHandler
{
    /**
     * @param SyncCounterInterface $counter Bare sync counter (never async-decorated)
     */
    public function __construct(private SyncCounterInterface $counter)
    {
    }

    /**
     * Processes a counter increment message by incrementing the sync counter.
     */
    #[AsMessageHandler]
    public function __invoke(CounterIncrementMessage $message): void
    {
        $this->counter->increment(ProductId::fromString($message->productId()));
    }
}
