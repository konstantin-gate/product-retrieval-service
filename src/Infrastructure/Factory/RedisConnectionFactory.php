<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

/**
 * Factory for creating pre-configured Redis connections from a DSN string.
 */
final readonly class RedisConnectionFactory
{
    /**
     * Creates a Redis connection from a DSN string.
     *
     * @param string $dsn Redis DSN (e.g. "redis://redis:6379")
     *
     * @return \Redis Configured Redis instance
     */
    public static function create(string $dsn): \Redis
    {
        $parsed = \parse_url($dsn);
        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? 6379;

        $redis = new \Redis();
        $redis->connect($host, $port);

        $db = isset($parsed['path']) ? (int) ltrim($parsed['path'], '/') : 0;
        if ($db > 0) {
            $redis->select($db);
        }

        return $redis;
    }
}
