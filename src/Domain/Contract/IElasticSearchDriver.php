<?php

declare(strict_types=1);

namespace App\Domain\Contract;

/**
 * Contract for ElasticSearch driver operations.
 *
 * Abstracts the ElasticSearch PHP client behind a domain port.
 */
interface IElasticSearchDriver
{
    /**
     * Finds a product document by ID in ElasticSearch.
     *
     * @param string $id Product identifier
     *
     * @return array<string, mixed> Raw product document source
     *
     * @throws \RuntimeException if the product is not found or ES is unavailable
     */
    public function findById(string $id): array;

    /**
     * Executes a raw search query in ElasticSearch.
     *
     * @param array<string, mixed> $params ElasticSearch client params
     *
     * @return array<string, mixed> Raw ES response
     */
    public function search(array $params): array;
}
