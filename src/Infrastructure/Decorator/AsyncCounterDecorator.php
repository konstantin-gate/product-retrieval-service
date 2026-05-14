<?php

declare(strict_types=1);

namespace App\Infrastructure\Decorator;

use App\Domain\Contract\CounterInterface;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Message\CounterIncrementMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Decorator that dispatches counter increments asynchronously via Symfony Messenger.
 *
 * If the message bus is unavailable, falls back to synchronous increment
 * to ensure counter data is not lost.
 */
final readonly class AsyncCounterDecorator implements CounterInterface
{
    public function __construct(
        private CounterInterface $fallbackCounter,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Dispatches a counter increment message to the async queue.
     *
     * Falls back to synchronous increment if the message bus is unavailable.
     *
     * @throws \RuntimeException if the fallback counter also fails
     */
    public function increment(ProductId $id): void
    {
        try {
            $this->messageBus->dispatch(new CounterIncrementMessage($id->value()));
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Async counter dispatch failed, falling back to sync increment', [
                'productId' => $id->value(),
                'exception' => $e,
            ]);
            $this->fallbackCounter->increment($id);
        }
    }

    public function getCount(ProductId $id): int
    {
        return $this->fallbackCounter->getCount($id);
    }
}
