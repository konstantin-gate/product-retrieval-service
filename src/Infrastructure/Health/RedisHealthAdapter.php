<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use App\Domain\Contract\HealthCheckInterface;
use Psr\Log\LoggerInterface;

/**
 * Health-check adapter for Redis.
 */
final readonly class RedisHealthAdapter implements HealthCheckInterface
{
    public function __construct(private \Redis $redis, private LoggerInterface $logger)
    {
    }

    public function isHealthy(): bool
    {
        try {
            /** @disregard P1013 — ext-redis stub missing, ping() exists at runtime */
            $result = $this->redis->ping();

            return true === $result || 'PONG' === $result;
        } catch (\Throwable $e) {
            $this->logger->warning('Redis health check failed: '.$e->getMessage());

            return false;
        }
    }
}
