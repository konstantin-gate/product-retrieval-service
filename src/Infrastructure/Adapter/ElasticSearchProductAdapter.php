<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Contract\IElasticSearchDriver;
use App\Domain\Contract\ProductSourceInterface;
use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\ProductId;

/**
 * ElasticSearch-based product source adapter.
 *
 * Delegates product retrieval to the IElasticSearchDriver port.
 */
final readonly class ElasticSearchProductAdapter implements ProductSourceInterface
{
    /**
     * @param IElasticSearchDriver $driver ElasticSearch driver implementation
     */
    public function __construct(private IElasticSearchDriver $driver)
    {
    }

    private const INDEX_NAME = 'products';

    public function findById(ProductId $id): ProductDTO
    {
        $data = $this->driver->findById($id->value());

        return ProductDTO::fromArray($data);
    }

    public function findSampleIds(int $limit): array
    {
        $response = $this->driver->search([
            'index' => self::INDEX_NAME,
            'size' => $limit,
            '_source' => false,
            'query' => ['match_all' => (object) []],
        ]);

        $ids = [];
        /** @var array<string, mixed> $hit */
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            if (\array_key_exists('_id', $hit) && null !== $hit['_id']) {
                $ids[] = (string) $hit['_id'];
            }
        }

        return $ids;
    }
}
