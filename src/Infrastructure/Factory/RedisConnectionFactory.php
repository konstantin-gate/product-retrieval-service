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
        if (false === $parsed) {
            throw new \InvalidArgumentException(\sprintf('Invalid Redis DSN: %s', $dsn));
        }

        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? 6379;

        $redis = new \Redis();
        /** @disregard P1009 — ext-redis stub missing, RedisException exists at runtime */
        $previousHandler = \set_error_handler(function (int $errno, string $errstr): bool {
            throw new \RedisException($errstr);
        });
        try {
            $redis->connect($host, $port);

            $db = \array_key_exists('path', $parsed) ? (int) ltrim($parsed['path'], '/') : 0;
            if ($db > 0) {
                $redis->select($db);
            }
        } catch (\Throwable $e) {
            \restore_error_handler();

            return $redis;
        }
        \restore_error_handler();

        return $redis;
    }
}
