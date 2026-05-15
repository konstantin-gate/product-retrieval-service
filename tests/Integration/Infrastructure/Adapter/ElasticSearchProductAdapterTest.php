<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapter;

use App\Domain\Exception\ProductNotFoundException;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Adapter\ElasticSearchProductAdapter;
use App\Infrastructure\Driver\SimpleElasticSearchDriver;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ElasticSearchProductAdapter against real ES (Docker).
 */
final class ElasticSearchProductAdapterTest extends TestCase
{
    private Client $esClient;
    private ElasticSearchProductAdapter $adapter;

    private const INDEX_NAME = 'products';
    private const TEST_ID_1 = '550e8400-e29b-41d4-a716-446655440011';
    private const TEST_ID_2 = '550e8400-e29b-41d4-a716-446655440012';
    private const TEST_ID_3 = '550e8400-e29b-41d4-a716-446655440013';
    private const TEST_IDS = [self::TEST_ID_1, self::TEST_ID_2, self::TEST_ID_3];

    protected function setUp(): void
    {
        $this->esClient = ClientBuilder::create()
            ->setHosts(['http://elasticsearch:9200'])
            ->build();

        // Ensure index exists — no swallowed exceptions
        $response = $this->esClient->indices()->exists(['index' => self::INDEX_NAME]);
        if (200 !== $response->getStatusCode()) {
            $this->esClient->indices()->create([
                'index' => self::INDEX_NAME,
                'body' => [
                    'mappings' => [
                        'properties' => [
                            'id' => ['type' => 'keyword'],
                            'name' => ['type' => 'text'],
                            'price' => ['type' => 'long'],
                            'description' => ['type' => 'text'],
                        ],
                    ],
                ],
            ]);
        }

        // Clean up any previous test data
        foreach (self::TEST_IDS as $testId) {
            try {
                $this->esClient->delete([
                    'index' => self::INDEX_NAME,
                    'id' => $testId,
                ]);
            } catch (ClientResponseException) {
                // Document may not exist — this is expected, not swallowed
            }
        }
        $this->esClient->indices()->refresh(['index' => self::INDEX_NAME]);

        // Index test documents
        $this->indexTestProduct(self::TEST_ID_1, 'ES Test Product', 1999, 'ES Test Description');
        $this->indexTestProduct(self::TEST_ID_2, 'ES Second Product', 2500, 'ES Second Description');
        $this->indexTestProduct(self::TEST_ID_3, 'ES Third Product', 3000, 'ES Third Description');

        $driver = new SimpleElasticSearchDriver($this->esClient);
        $this->adapter = new ElasticSearchProductAdapter($driver);
    }

    private function indexTestProduct(string $id, string $name, int $price, string $description): void
    {
        $this->esClient->index([
            'index' => self::INDEX_NAME,
            'id' => $id,
            'body' => [
                'id' => $id,
                'name' => $name,
                'price' => $price,
                'description' => $description,
            ],
            'refresh' => 'true',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (self::TEST_IDS as $testId) {
            try {
                $this->esClient->delete([
                    'index' => self::INDEX_NAME,
                    'id' => $testId,
                ]);
            } catch (ClientResponseException) {
                // Document may not exist — expected in tearDown
            }
        }
        $this->esClient->indices()->refresh(['index' => self::INDEX_NAME]);
    }

    public function testFindByIdReturnsProductDtoForExistingProduct(): void
    {
        $product = $this->adapter->findById(ProductId::fromString(self::TEST_ID_1));

        self::assertSame(self::TEST_ID_1, $product->id->value());
        self::assertSame('ES Test Product', $product->name);
        self::assertSame('1999', $product->price->amount());
        self::assertSame('ES Test Description', $product->description);
    }

    public function testFindByIdThrowsForMissingProduct(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->adapter->findById(ProductId::fromString('00000000-0000-0000-0000-000000000000'));
    }

    public function testFindSampleIdsReturnsLimitedList(): void
    {
        $ids = $this->adapter->findSampleIds(2);

        self::assertCount(2, $ids);
    }

    public function testFindSampleIdsRespectsLimit(): void
    {
        $ids = $this->adapter->findSampleIds(1);

        self::assertCount(1, $ids);
    }
}
