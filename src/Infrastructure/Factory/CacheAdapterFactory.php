<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Contract\CacheInterface;
use App\Infrastructure\Cache\FileCacheAdapter;
use App\Infrastructure\Cache\NullCacheAdapter;
use App\Infrastructure\Cache\RedisCacheAdapter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Factory for creating CacheInterface implementations based on configuration.
 *
 * Supports "file", "redis", and "null" cache types.
 */
final readonly class CacheAdapterFactory
{
    private const TYPE_FILE = 'file';
    private const TYPE_REDIS = 'redis';
    private const TYPE_NULL = 'null';

    public function __construct(
        private NormalizerInterface&DenormalizerInterface $serializer,
        private \Redis $redis,
        private string $cacheDir,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Creates a cache adapter based on the specified type.
     *
     * @param string $type Cache type: "file", "redis", or "null"
     *
     * @throws \InvalidArgumentException if the cache type is unsupported
     */
    public function create(string $type): CacheInterface
    {
        return match ($type) {
            self::TYPE_FILE => $this->createFileCache(),
            self::TYPE_REDIS => $this->createRedisCache(),
            self::TYPE_NULL => new NullCacheAdapter(),
            default => throw new \InvalidArgumentException(\sprintf('Unsupported cache type: %s', $type)),
        };
    }

    private function createFileCache(): FileCacheAdapter
    {
        $cache = new FilesystemAdapter('cache', 0, $this->cacheDir);

        return new FileCacheAdapter($cache, $this->serializer, $this->logger);
    }

    private function createRedisCache(): RedisCacheAdapter
    {
        $cache = new RedisAdapter($this->redis, 'cache', 0);

        return new RedisCacheAdapter($cache, $this->serializer, $this->logger);
    }
}
