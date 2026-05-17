<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Contract\ConfigInterface;
use App\Domain\Contract\EnvFileWriterInterface;
use App\Domain\Contract\HealthCheckInterface;
use App\Domain\Contract\SampleIdProviderInterface;
use App\Domain\Contract\SeederInterface;

/**
 * Manages runtime configuration toggles and persists changes to .env.local.
 */
final readonly class DashboardManager
{
    private const ALL_ALLOWED_TOGGLES = [
        'ACTIVE_PRODUCT_SOURCE' => ['elasticsearch', 'mysql'],
        'ACTIVE_CACHE_DRIVER' => ['file', 'redis', 'null'],
        'ACTIVE_COUNTER_MODE' => ['async', 'filesystem', 'redis', 'null'],
    ];

    private const REDIS_DEPENDENT_OPTIONS = [
        'ACTIVE_CACHE_DRIVER' => ['redis'],
        'ACTIVE_COUNTER_MODE' => ['redis', 'async'],
    ];

    /**
     * @param string                    $projectDir          Root directory of the project
     * @param EnvFileWriterInterface    $envFileWriter       Environment file writer port
     * @param ConfigInterface           $config              Application configuration
     * @param SampleIdProviderInterface $source              Sample ID provider
     * @param HealthCheckInterface      $mysqlHealth         MySQL health-check adapter
     * @param HealthCheckInterface      $elasticSearchHealth ElasticSearch health-check adapter
     * @param HealthCheckInterface      $redisHealth         Redis health-check adapter
     * @param SeederInterface           $seeder              Product seeder
     */
    public function __construct(
        private string $projectDir,
        private EnvFileWriterInterface $envFileWriter,
        private ConfigInterface $config,
        private SampleIdProviderInterface $source,
        private HealthCheckInterface $mysqlHealth,
        private HealthCheckInterface $elasticSearchHealth,
        private HealthCheckInterface $redisHealth,
        private SeederInterface $seeder,
    ) {
    }

    /**
     * Updates or adds a configuration toggle in .env.local.
     *
     * Reads existing file via Symfony Filesystem (no raw PHP file functions).
     * Skips empty lines and comments (lines starting with #).
     *
     * @param string $key   Environment variable name
     * @param string $value New value for the variable
     */
    public function setToggle(string $key, string $value): void
    {
        $envLocalPath = $this->projectDir.'/.env.local';
        $lines = [];

        $content = $this->envFileWriter->readFile($envLocalPath);
        if ('' !== $content) {
            $lines = \explode("\n", $content);
        }

        $keyFound = false;
        foreach ($lines as $index => $line) {
            $trimmed = \trim($line);
            if ('' === $trimmed || \str_starts_with($trimmed, '#')) {
                continue;
            }
            if (\str_starts_with($trimmed, $key.'=')) {
                $pos = \strpos($line, '=');
                if (false !== $pos) {
                    $oldValue = \substr($line, $pos + 1);
                    if (\str_starts_with($oldValue, '"') && \str_ends_with($oldValue, '"')) {
                        $lines[$index] = $key.'="'.$value.'"';
                    } else {
                        $lines[$index] = $key.'='.$value;
                    }
                    $keyFound = true;
                }

                break;
            }
        }

        if (!$keyFound) {
            $lines[] = $key.'='.$value;
        }

        $this->envFileWriter->writeFile($envLocalPath, \implode("\n", $lines)."\n");
    }

    /**
     * @return array{source: string, cache: string, counter: string}
     */
    public function getCurrentConfig(): array
    {
        return [
            'source' => $this->config->getDataSource(),
            'cache' => $this->config->getCacheDriver(),
            'counter' => $this->config->getCounterMode(),
        ];
    }

    /**
     * @return array{mysql: bool, elasticsearch: bool, redis: bool}
     */
    public function getHealthStatus(): array
    {
        return [
            'mysql' => $this->mysqlHealth->isHealthy(),
            'elasticsearch' => $this->elasticSearchHealth->isHealthy(),
            'redis' => $this->redisHealth->isHealthy(),
        ];
    }

    /**
     * @param int<1, max> $limit
     *
     * @return list<string>
     */
    public function getSampleProductIds(int $limit): array
    {
        return $this->source->findSampleIds($limit);
    }

    /**
     * Seeds product data into storage backends.
     *
     * @param int<1, max> $count Number of products to generate
     */
    public function seed(int $count): void
    {
        $this->seeder->seed($count);
    }

    /**
     * Returns the full list of all possible toggle options, regardless of service health.
     *
     * @return array<string, list<string>>
     */
    public function getAllToggleOptions(): array
    {
        return self::ALL_ALLOWED_TOGGLES;
    }

    /**
     * Returns available toggle options, excluding Redis-dependent ones when Redis is unavailable.
     *
     * @param bool|null $redisHealthy Pre-computed Redis health status to avoid redundant pings
     *
     * @return array<string, list<string>>
     */
    public function getAvailableToggles(?bool $redisHealthy = null): array
    {
        $toggles = self::ALL_ALLOWED_TOGGLES;
        $redisHealthy ??= $this->redisHealth->isHealthy();

        if ($redisHealthy === false) {
            foreach (self::REDIS_DEPENDENT_OPTIONS as $key => $options) {
                $toggles[$key] = \array_values(\array_diff($toggles[$key], $options));
            }
        }

        return $toggles;
    }

    /**
     * Ensures current configuration does not use unavailable services.
     *
     * If Redis is unavailable and current config uses Redis-dependent options,
     * auto-switches to safe fallback values and persists them to .env.local.
     *
     * @param bool|null $redisHealthy Pre-computed Redis health status to avoid redundant pings
     *
     * @return bool True if configuration was changed, false otherwise
     */
    public function ensureConfigurationValidity(?bool $redisHealthy = null): bool
    {
        $redisHealthy ??= $this->redisHealth->isHealthy();
        if ($redisHealthy === true) {
            return false;
        }

        $config = $this->getCurrentConfig();
        $changed = false;

        if ('redis' === $config['cache']) {
            $this->setToggle('ACTIVE_CACHE_DRIVER', 'file');
            $changed = true;
        }

        if ('redis' === $config['counter'] || 'async' === $config['counter']) {
            $this->setToggle('ACTIVE_COUNTER_MODE', 'filesystem');
            $changed = true;
        }

        return $changed;
    }
}
