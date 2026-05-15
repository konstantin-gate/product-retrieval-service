<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Contract\ConfigInterface;
use App\Domain\Contract\HealthCheckInterface;
use App\Domain\Contract\ProductSourceInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manages runtime configuration toggles and persists changes to .env.local.
 */
final readonly class DashboardManager
{
    /**
     * @param string                 $projectDir          Root directory of the project
     * @param Filesystem             $filesystem          Symfony Filesystem component
     * @param ConfigInterface        $config              Application configuration
     * @param ProductSourceInterface $source              Active product source
     * @param HealthCheckInterface   $mysqlHealth         MySQL health-check adapter
     * @param HealthCheckInterface   $elasticSearchHealth ElasticSearch health-check adapter
     * @param HealthCheckInterface   $redisHealth         Redis health-check adapter
     */
    public function __construct(
        private string $projectDir,
        private Filesystem $filesystem,
        private ConfigInterface $config,
        private ProductSourceInterface $source,
        private HealthCheckInterface $mysqlHealth,
        private HealthCheckInterface $elasticSearchHealth,
        private HealthCheckInterface $redisHealth,
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

        if ($this->filesystem->exists($envLocalPath)) {
            $content = $this->filesystem->readFile($envLocalPath);
            $lines = \explode("\n", $content);
        }

        $keyFound = false;
        foreach ($lines as $index => $line) {
            $trimmed = \trim($line);
            if ('' === $trimmed || \str_starts_with($trimmed, '#')) {
                continue;
            }
            if (\str_starts_with($trimmed, $key.'=')) {
                $lines[$index] = $key.'='.$value;
                $keyFound = true;

                break;
            }
        }

        if (!$keyFound) {
            $lines[] = $key.'='.$value;
        }

        $filtered = \array_filter($lines, static fn (string $line): bool => '' !== \trim($line));
        $this->filesystem->dumpFile($envLocalPath, \implode("\n", $filtered)."\n");
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
}
