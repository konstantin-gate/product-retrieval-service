<?php

declare(strict_types=1);

namespace App\Domain\Contract;

/**
 * Contract for health-check adapters.
 *
 * Abstracts infrastructure-specific connection checks behind a domain port,
 * preserving the dependency direction: Application -> Domain <- Infrastructure.
 */
interface HealthCheckInterface
{
    /**
     * Checks if the underlying service is healthy and reachable.
     *
     * @return bool True if the service responds correctly, false otherwise
     */
    public function isHealthy(): bool;
}
