<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use App\Domain\Contract\ConfigInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Configuration implementation backed by Symfony ParameterBagInterface.
 *
 * Reads application settings from environment-based parameters.
 */
final readonly class EnvConfig implements ConfigInterface
{
    public function __construct(private ParameterBagInterface $parameterBag)
    {
    }

    public function getString(string $key): string
    {
        $value = $this->parameterBag->get($key);
        if (!\is_scalar($value) && null !== $value) {
            throw new \RuntimeException(\sprintf('Parameter "%s" must be a scalar value.', $key));
        }

        return (string) $value;
    }

    public function getInt(string $key): int
    {
        $value = $this->parameterBag->get($key);
        if (!\is_scalar($value) && null !== $value) {
            throw new \RuntimeException(\sprintf('Parameter "%s" must be a scalar value.', $key));
        }

        return (int) $value;
    }

    public function getBool(string $key): bool
    {
        return (bool) $this->parameterBag->get($key);
    }

    public function getDataSource(): string
    {
        return $this->getString('app.active_product_source');
    }

    public function getCacheDriver(): string
    {
        return $this->getString('app.active_cache_driver');
    }

    public function getCounterMode(): string
    {
        return $this->getString('app.active_counter_mode');
    }

    public function getEsIndexName(): string
    {
        return $this->getString('app.es_index_name');
    }
}
