<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use App\Domain\Contract\HealthCheckInterface;

/**
 * Health-check adapter for MySQL via PDO.
 */
final readonly class MySqlHealthAdapter implements HealthCheckInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function isHealthy(): bool
    {
        try {
            $this->pdo->query('SELECT 1');

            return true;
        } catch (\PDOException) {
            return false;
        }
    }
}
