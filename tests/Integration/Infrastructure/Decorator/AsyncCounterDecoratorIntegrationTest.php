<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Decorator;

use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Counter\FilesystemCounter;
use App\Infrastructure\Decorator\AsyncCounterDecorator;
use App\Infrastructure\Message\CounterIncrementMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Integration tests for AsyncCounterDecorator with real transport behaviors.
 */
final class AsyncCounterDecoratorIntegrationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir().'/logio_async_test_'.\uniqid();
        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testIntegrationDispatchAndConsume(): void
    {
        $productId = ProductId::fromString('550e8400-e29b-41d4-a716-446655440200');

        $fsAdapter = new FilesystemAdapter('counter', 0, $this->tempDir);
        $fallbackCounter = new FilesystemCounter($fsAdapter, new NullLogger());

        $syncBus = new class($fallbackCounter) implements MessageBusInterface {
            public function __construct(private FilesystemCounter $counter)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                if ($message instanceof CounterIncrementMessage) {
                    $id = ProductId::fromString($message->productId());
                    $this->counter->increment($id);
                }

                return new Envelope($message, $stamps);
            }
        };

        $decorator = new AsyncCounterDecorator($fallbackCounter, $syncBus, new NullLogger());

        $decorator->increment($productId);

        self::assertSame(1, $fallbackCounter->getCount($productId));
    }

    public function testIntegrationFallbackOnDispatchFailure(): void
    {
        $productId = ProductId::fromString('550e8400-e29b-41d4-a716-446655440201');

        $fsAdapter = new FilesystemAdapter('counter', 0, $this->tempDir);
        $fallbackCounter = new FilesystemCounter($fsAdapter, new NullLogger());

        $failingBus = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new class extends \RuntimeException implements \Symfony\Component\Messenger\Exception\ExceptionInterface {
                };
            }
        };

        $decorator = new AsyncCounterDecorator($fallbackCounter, $failingBus, new NullLogger());

        $decorator->increment($productId);

        self::assertSame(1, $fallbackCounter->getCount($productId));
    }
}
