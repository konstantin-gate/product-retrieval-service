<?php

declare(strict_types=1);

namespace App\Infrastructure\Health;

use App\Domain\Contract\HealthCheckInterface;
use Elastic\Elasticsearch\Client;

/**
 * Health-check adapter for ElasticSearch.
 */
final readonly class ElasticSearchHealthAdapter implements HealthCheckInterface
{
    public function __construct(private Client $client)
    {
    }

    public function isHealthy(): bool
    {
        try {
            $this->client->info();

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
