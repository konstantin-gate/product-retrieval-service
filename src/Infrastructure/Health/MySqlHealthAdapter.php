<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use App\Domain\Contract\HealthCheckInterface;
use Psr\Log\LoggerInterface;

/**
 * Health-check adapter for MySQL via PDO.
 */
final readonly class MySqlHealthAdapter implements HealthCheckInterface
{
    public function __construct(private \PDO $pdo, private LoggerInterface $logger)
    {
    }

    public function isHealthy(): bool
    {
        try {
            $this->pdo->query('SELECT 1');

            return true;
        } catch (\PDOException $e) {
            $this->logger->warning('MySQL health check failed: '.$e->getMessage());

            return false;
        }
    }
}
