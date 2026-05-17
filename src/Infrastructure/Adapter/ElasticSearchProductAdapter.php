<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Contract\IElasticSearchDriver;
use App\Domain\Contract\ProductSourceInterface;
use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Factory\ProductDTOFactory;

/**
 * ElasticSearch-based product source adapter.
 *
 * Delegates product retrieval to the IElasticSearchDriver port.
 */
final readonly class ElasticSearchProductAdapter implements ProductSourceInterface
{
    /**
     * @param IElasticSearchDriver $driver      ElasticSearch driver implementation
     * @param string               $esIndexName ElasticSearch index name for product data
     */
    public function __construct(
        private IElasticSearchDriver $driver,
        private string $esIndexName,
    ) {
    }

    public function findById(ProductId $id): ProductDTO
    {
        $data = $this->driver->findById($id->value());

        return ProductDTOFactory::fromArray($data);
    }

    public function findSampleIds(int $limit): array
    {
        $response = $this->driver->search([
            'index' => $this->esIndexName,
            'size' => $limit,
            '_source' => false,
            'query' => ['match_all' => (object) []],
        ]);

        if (!\array_key_exists('hits', $response) || !\is_array($response['hits']) || !\array_key_exists('hits', $response['hits'])) {
            throw new \RuntimeException('Unexpected ElasticSearch response structure in findSampleIds');
        }

        $ids = [];
        /** @var array<string, mixed> $hit */
        foreach ($response['hits']['hits'] as $hit) {
            if (\array_key_exists('_id', $hit) && (is_string($hit['_id']) || is_int($hit['_id']))) {
                $ids[] = (string) $hit['_id'];
            }
        }

        return $ids;
    }
}
