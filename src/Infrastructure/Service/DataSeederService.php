<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\DTO\ProductDTO;
use App\Domain\Exception\SourceUnavailableException;
use Elastic\Elasticsearch\Client;

/**
 * Persists generated product data to MySQL and ElasticSearch.
 */
final readonly class DataSeederService
{
    private const INDEX_NAME = 'products';
    private const ES_BULK_BATCH_SIZE = 200;
    private const MYSQL_BATCH_SIZE = 100;

    public function __construct(
        private \PDO $pdo,
        private Client $client,
    ) {
    }

    /**
     * Writes products to both storages.
     *
     * @param list<ProductDTO>         $products Products to seed
     * @param \Closure(int): void|null $onChunk  Optional callback invoked after each chunk with chunk size
     *
     * @throws SourceUnavailableException if MySQL or ES write fails
     */
    public function seed(array $products, ?\Closure $onChunk = null): void
    {
        $this->seedMySql($products, $onChunk);
        $this->seedElasticSearch($products, $onChunk);
    }

    /**
     * @param list<ProductDTO>         $products
     * @param \Closure(int): void|null $onChunk
     */
    private function seedMySql(array $products, ?\Closure $onChunk = null): void
    {
        $this->pdo->beginTransaction();
        try {
            $chunks = \array_chunk($products, self::MYSQL_BATCH_SIZE);
            foreach ($chunks as $chunk) {
                $this->insertBatch($chunk);
                if (null !== $onChunk) {
                    ($onChunk)(\count($chunk));
                }
            }
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw new SourceUnavailableException('MySQL seed failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Inserts a batch of products in a single prepared statement.
     *
     * @param list<ProductDTO> $products
     */
    private function insertBatch(array $products): void
    {
        $values = [];
        $params = [];
        $index = 0;

        foreach ($products as $product) {
            $values[] = "(:id{$index}, :name{$index}, :price{$index}, :description{$index})";
            $params[":id{$index}"] = $product->id->value();
            $params[":name{$index}"] = $product->name;
            $params[":price{$index}"] = (int) $product->price->amount();
            $params[":description{$index}"] = $product->description;
            ++$index;
        }

        $sql = 'INSERT INTO products (id, name, price, description) VALUES '.\implode(', ', $values);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @param list<ProductDTO>         $products
     * @param \Closure(int): void|null $onChunk
     */
    private function seedElasticSearch(array $products, ?\Closure $onChunk = null): void
    {
        $chunks = \array_chunk($products, self::ES_BULK_BATCH_SIZE);
        foreach ($chunks as $chunk) {
            $bulkBody = [];
            foreach ($chunk as $product) {
                $bulkBody[] = ['index' => ['_index' => self::INDEX_NAME, '_id' => $product->id->value()]];
                $bulkBody[] = [
                    'id' => $product->id->value(),
                    'name' => $product->name,
                    'price' => (int) $product->price->amount(),
                    'description' => $product->description,
                ];
            }

            try {
                $this->client->bulk(['body' => $bulkBody]);
                if (null !== $onChunk) {
                    ($onChunk)(\count($chunk));
                }
            } catch (\Exception $e) {
                throw new SourceUnavailableException('ElasticSearch seed failed: '.$e->getMessage(), 0, $e);
            }
        }
    }
}
