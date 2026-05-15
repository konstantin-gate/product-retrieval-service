<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use PDO;

/**
 * Parses Symfony-style DATABASE_URL into PDO DSN and creates connection.
 */
final readonly class PdoConnectionFactory
{
    public static function create(string $databaseUrl): \PDO
    {
        $parsed = parse_url($databaseUrl);
        if (false === $parsed || !\array_key_exists('host', $parsed) || !\array_key_exists('path', $parsed)) {
            throw new \InvalidArgumentException('Invalid DATABASE_URL format');
        }

        $host = $parsed['host'];
        /** @phpstan-ignore-next-line */
        $port = \array_key_exists('port', $parsed) ? (int) $parsed['port'] : 3306;
        $dbname = ltrim($parsed['path'], '/');
        $user = \array_key_exists('user', $parsed) ? $parsed['user'] : 'root';
        $password = \array_key_exists('pass', $parsed) ? $parsed['pass'] : '';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbname);

        return new \PDO($dsn, $user, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
