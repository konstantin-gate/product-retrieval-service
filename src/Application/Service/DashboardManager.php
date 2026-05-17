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
}
