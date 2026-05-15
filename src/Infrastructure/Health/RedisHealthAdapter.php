<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use App\Domain\Contract\HealthCheckInterface;

/**
 * Health-check adapter for Redis.
 */
final readonly class RedisHealthAdapter implements HealthCheckInterface
{
    public function __construct(private ?\Redis $redis = null)
    {
    }

    public function isHealthy(): bool
    {
        if (null === $this->redis) {
            return false;
        }

        try {
            return 'PONG' === $this->redis->ping();
        } catch (\Exception) {
            return false;
        }
    }
}
