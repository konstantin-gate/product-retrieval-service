<?php

declare(strict_types=1);

namespace App\Domain\Contract;

/**
 * Contract for reading application configuration values.
 *
 * Typically implemented via Symfony ParameterBagInterface.
 */
interface ConfigInterface
{
    /**
     * Returns a configuration value as a string.
     */
    public function getString(string $key): string;

    /**
     * Returns a configuration value as an integer.
     */
    public function getInt(string $key): int;

    /**
     * Returns a configuration value as a boolean.
     */
    public function getBool(string $key): bool;

    /**
     * Checks whether a configuration key exists.
     */
    public function has(string $key): bool;

    /**
     * Returns the active product data source name (e.g. "mysql", "elasticsearch").
     */
    public function getDataSource(): string;

    /**
     * Returns the active cache driver name (e.g. "file", "redis", "null").
     */
    public function getCacheDriver(): string;

    /**
     * Returns the active counter mode name (e.g. "filesystem", "redis", "async", "null").
     */
    public function getCounterMode(): string;
}
