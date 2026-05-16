<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Contract\ConfigInterface;
use App\Domain\Contract\IElasticSearchDriver;
use App\Domain\Contract\IMysqlDriver;
use App\Domain\Contract\ProductSourceInterface;
use App\Infrastructure\Adapter\ElasticSearchProductAdapter;
use App\Infrastructure\Adapter\MySqlProductAdapter;

/**
 * Factory for creating ProductSourceInterface implementations based on configuration.
 *
 * Supports "elasticsearch" and "mysql" product sources.
 */
final readonly class ProductSourceFactory
{
    private const SOURCE_ELASTICSEARCH = 'elasticsearch';
    private const SOURCE_MYSQL = 'mysql';

    public function __construct(
        private ConfigInterface $config,
        private IElasticSearchDriver $esDriver,
        private IMysqlDriver $mysqlDriver,
    ) {
    }

    public function create(): ProductSourceInterface
    {
        return match ($this->config->getDataSource()) {
            self::SOURCE_ELASTICSEARCH => new ElasticSearchProductAdapter($this->esDriver, $this->config->getEsIndexName()),
            self::SOURCE_MYSQL => new MySqlProductAdapter($this->mysqlDriver),
            default => throw new \RuntimeException('Unknown product source: '.$this->config->getDataSource()),
        };
    }
}
