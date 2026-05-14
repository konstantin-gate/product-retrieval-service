<?php

declare(strict_types=1);

namespace App\Infrastructure\Driver;

use App\Domain\Contract\IElasticSearchDriver;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\Exception\SourceUnavailableException;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;

/**
 * ElasticSearch driver implementation using the official PHP client.
 */
final readonly class SimpleElasticSearchDriver implements IElasticSearchDriver
{
    private const PRODUCTS_INDEX_NAME = 'products';

    /**
     * @param Client $client Pre-configured ElasticSearch client
     */
    public function __construct(private Client $client)
    {
    }

    /**
     * Finds a product document by ID in ElasticSearch.
     *
     * @param string $id Product identifier
     *
     * @return array<string, mixed> Product document source
     *
     * @throws ProductNotFoundException   if the product does not exist in the index
     * @throws SourceUnavailableException if the ElasticSearch connection fails
     */
    public function findById(string $id): array
    {
        try {
            $response = $this->client->get([
                'index' => self::PRODUCTS_INDEX_NAME,
                'id' => $id,
            ]);
        } catch (ClientResponseException $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
                throw new ProductNotFoundException('Product not found: '.$id, 0, $e);
            }
            throw new SourceUnavailableException('ElasticSearch error: '.$e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            throw new SourceUnavailableException('ElasticSearch error: '.$e->getMessage(), 0, $e);
        }

        /** @var array<string, mixed>|null $source */
        $source = $response['_source'] ?? null;
        if (!\is_array($source)) {
            throw new SourceUnavailableException('Invalid response from ElasticSearch');
        }

        return $source;
    }
}
