<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Contract\CounterInterface;
use App\Infrastructure\Counter\FilesystemCounter;
use App\Infrastructure\Counter\NullCounter;
use App\Infrastructure\Counter\RedisCounter;
use App\Infrastructure\Decorator\AsyncCounterDecorator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Factory for creating CounterInterface implementations based on configuration.
 *
 * Supports "filesystem", "redis", "async", and "null" counter modes.
 */
final readonly class CounterAdapterFactory
{
    private const MODE_ASYNC = 'async';
    private const MODE_FILESYSTEM = 'filesystem';
    private const MODE_REDIS = 'redis';
    private const MODE_NULL = 'null';

    public function __construct(
        private MessageBusInterface $messageBus,
        private \Redis $redis,
        private string $counterDir,
        private LoggerInterface $logger,
        private string $counterBaseMode,
    ) {
    }

    /**
     * Creates a counter adapter based on the specified mode.
     *
     * When mode is "async", wraps the sync counter in AsyncCounterDecorator.
     *
     * @param string $mode Counter mode: "filesystem", "redis", "async", or "null"
     *
     * @throws \InvalidArgumentException if the counter mode is unsupported
     */
    public function create(string $mode): CounterInterface
    {
        $syncCounter = $this->createSync($mode);

        return match ($mode) {
            self::MODE_ASYNC => new AsyncCounterDecorator($syncCounter, $this->messageBus, $this->logger),
            default => $syncCounter,
        };
    }

    /**
     * Creates a synchronous counter without async decoration.
     *
     * Used by CounterIncrementHandler to persist counter increments
     * from the async queue, avoiding recursive dispatch.
     *
     * @param string $mode Base counter mode: "filesystem", "redis", or "null".
     *                     When "async" is passed, resolves to $counterBaseMode.
     *
     * @throws \InvalidArgumentException if the counter mode is unsupported
     */
    public function createSync(string $mode): CounterInterface
    {
        $realMode = self::MODE_ASYNC === $mode ? $this->counterBaseMode : $mode;

        return match ($realMode) {
            self::MODE_FILESYSTEM => $this->createFilesystemCounter(),
            self::MODE_REDIS => $this->createRedisCounter(),
            self::MODE_NULL => new NullCounter(),
            default => throw new \InvalidArgumentException(\sprintf('Unsupported counter base mode: %s', $realMode)),
        };
    }

    private function createFilesystemCounter(): FilesystemCounter
    {
        $cache = new FilesystemAdapter('counter', 0, $this->counterDir);

        return new FilesystemCounter($cache, $this->logger);
    }

    private function createRedisCounter(): RedisCounter
    {
        return new RedisCounter($this->redis, $this->logger);
    }
}
