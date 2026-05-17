<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Contract\CacheInterface;
use App\Domain\DTO\ProductDTO;
use App\Domain\Exception\CacheException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * File-based cache adapter using Symfony FilesystemAdapter.
 */
final readonly class FileCacheAdapter implements CacheInterface
{
    /**
     * @param FilesystemAdapter                         $cache      Symfony filesystem cache adapter
     * @param NormalizerInterface&DenormalizerInterface $serializer Serializer for DTO normalization
     * @param LoggerInterface                           $logger     Logger for cache operation errors
     */
    public function __construct(
        private FilesystemAdapter $cache,
        private NormalizerInterface&DenormalizerInterface $serializer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Retrieves a cached product by key.
     *
     * @return ProductDTO|null Cached product, or null on cache miss
     *
     * @throws CacheException if cache read fails
     */
    public function get(string $key): ?ProductDTO
    {
        try {
            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {
                return null;
            }

            return $this->serializer->denormalize($item->get(), ProductDTO::class);
        } catch (\Throwable $e) {
            $this->logger->error('Cache read failed', [
                'key' => $key,
                'driver' => 'file',
                'exception' => $e->getMessage(),
            ]);
            throw new CacheException('Cache read error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Stores a product in the file cache.
     *
     * @param string     $key   Cache key
     * @param ProductDTO $value Product to cache
     * @param int|null   $ttl   Time-to-live in seconds, or null for permanent cache
     *
     * @throws CacheException if cache write fails
     */
    public function set(string $key, ProductDTO $value, ?int $ttl = null): void
    {
        try {
            $item = $this->cache->getItem($key);
            $item->set($this->serializer->normalize($value, 'json'));
            $item->expiresAfter($ttl);
            $this->cache->save($item);
        } catch (\Throwable $e) {
            $this->logger->error('Cache write failed', [
                'key' => $key,
                'driver' => 'file',
                'exception' => $e->getMessage(),
            ]);
            throw new CacheException('Cache write error: '.$e->getMessage(), 0, $e);
        }
    }
}
