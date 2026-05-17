<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapter;

use App\Domain\Contract\ConfigInterface;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Adapter\ElasticSearchProductAdapter;
use App\Infrastructure\Driver\SimpleElasticSearchDriver;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for ElasticSearchProductAdapter against real ES (Docker).
 */
final class ElasticSearchProductAdapterTest extends KernelTestCase
{
    private Client $esClient;
    private ElasticSearchProductAdapter $adapter;
    private string $esIndexName;

    private const TEST_ID_1 = '550e8400-e29b-41d4-a716-446655440011';
    private const TEST_ID_2 = '550e8400-e29b-41d4-a716-446655440012';
    private const TEST_ID_3 = '550e8400-e29b-41d4-a716-446655440013';
    private const TEST_IDS = [self::TEST_ID_1, self::TEST_ID_2, self::TEST_ID_3];

    protected function setUp(): void
    {
        $container = static::getContainer();

        $client = $container->get(Client::class);
        if (!$client instanceof Client) {
            throw new \RuntimeException('ElasticSearch client not found in container');
        }
        $this->esClient = $client;

        $config = $container->get(ConfigInterface::class);
        if (!$config instanceof ConfigInterface) {
            throw new \RuntimeException('Config service not found in container');
        }
        $this->esIndexName = $config->getEsIndexName();

        // Ensure index exists
        $response = $this->esClient->indices()->exists(['index' => $this->esIndexName]);
        if (!$response instanceof Elasticsearch) {
            throw new \RuntimeException('Unexpected response type from ElasticSearch');
        }

        if (200 !== $response->getStatusCode()) {
            $this->esClient->indices()->create([
                'index' => $this->esIndexName,
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
                    'index' => $this->esIndexName,
                    'id' => $testId,
                ]);
            } catch (ClientResponseException) {
                // Document may not exist — this is expected, not swallowed
            }
        }
        $this->esClient->indices()->refresh(['index' => $this->esIndexName]);

        // Index test documents
        $this->indexTestProduct(self::TEST_ID_1, 'ES Test Product', 1999, 'ES Test Description');
        $this->indexTestProduct(self::TEST_ID_2, 'ES Second Product', 2500, 'ES Second Description');
        $this->indexTestProduct(self::TEST_ID_3, 'ES Third Product', 3000, 'ES Third Description');

        $driver = new SimpleElasticSearchDriver($this->esClient, $this->esIndexName);
        $this->adapter = new ElasticSearchProductAdapter($driver, $this->esIndexName);
    }

    private function indexTestProduct(string $id, string $name, int $price, string $description): void
    {
        $this->esClient->index([
            'index' => $this->esIndexName,
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
                    'index' => $this->esIndexName,
                    'id' => $testId,
                ]);
            } catch (ClientResponseException) {
                // Document may not exist — expected in tearDown
            }
        }
        $this->esClient->indices()->refresh(['index' => $this->esIndexName]);
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
