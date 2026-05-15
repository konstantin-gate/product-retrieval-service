<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Decorator;

use App\Domain\Contract\CounterInterface;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Decorator\AsyncCounterDecorator;
use App\Infrastructure\Message\CounterIncrementMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tests for AsyncCounterDecorator.
 */
final class AsyncCounterDecoratorTest extends TestCase
{
    private CounterInterface&MockObject $fallbackCounter;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private AsyncCounterDecorator $decorator;

    protected function setUp(): void
    {
        $this->fallbackCounter = $this->createMock(CounterInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->decorator = new AsyncCounterDecorator(
            $this->fallbackCounter,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testIncrementDispatchesMessageAndDoesNotCallFallback(): void
    {
        $productId = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function (CounterIncrementMessage $message) use ($productId): bool {
                return $message->productId() === $productId->value();
            }))
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));
        $this->fallbackCounter->expects($this->never())->method('increment');

        $this->decorator->increment($productId);
    }

    public function testIncrementFallsBackToSyncCounterOnDispatchFailure(): void
    {
        $productId = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $exception = $this->createMock(ExceptionInterface::class);
        $this->messageBus->method('dispatch')->willThrowException($exception);
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                self::stringContains('Async counter dispatch failed'),
                self::arrayHasKey('productId'),
            );
        $this->fallbackCounter->expects($this->once())
            ->method('increment')
            ->with($productId);

        $this->decorator->increment($productId);
    }

    public function testGetCountDelegatesToFallbackCounter(): void
    {
        $productId = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $this->fallbackCounter->method('getCount')->willReturn(42);

        self::assertSame(42, $this->decorator->getCount($productId));
    }
}
