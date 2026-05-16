<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use App\Domain\Contract\HealthCheckInterface;
use Elastic\Elasticsearch\Client;
use Psr\Log\LoggerInterface;

/**
 * Health-check adapter for ElasticSearch.
 */
final readonly class ElasticSearchHealthAdapter implements HealthCheckInterface
{
    public function __construct(private Client $client, private LoggerInterface $logger)
    {
    }

    public function isHealthy(): bool
    {
        try {
            $this->client->info();

            return true;
        } catch (\Exception $e) {
            $this->logger->warning('ElasticSearch health check failed: '.$e->getMessage());

            return false;
        }
    }
}
