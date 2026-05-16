<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Driver;

use App\Domain\Contract\ConfigInterface;
use App\Domain\Exception\ProductNotFoundException;
use App\Infrastructure\Driver\SimpleElasticSearchDriver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for SimpleElasticSearchDriver against real ES (Docker).
 */
final class SimpleElasticSearchDriverTest extends KernelTestCase
{
    private \Elastic\Elasticsearch\Client $esClient;
    private SimpleElasticSearchDriver $driver;
    private string $esIndexName;

    private const TEST_ID = '550e8400-e29b-41d4-a716-446655440110';

    protected function setUp(): void
    {
        $container = static::getContainer();

        $this->esClient = $container->get(\Elastic\Elasticsearch\Client::class);
        $this->esIndexName = $container->get(ConfigInterface::class)->getEsIndexName();

        $this->esClient->index([
            'index' => $this->esIndexName,
            'id' => self::TEST_ID,
            'body' => [
                'id' => self::TEST_ID,
                'name' => 'ES Driver Test Product',
                'price' => 3999,
                'description' => 'ES driver test description',
            ],
            'refresh' => 'true',
        ]);

        $this->driver = new SimpleElasticSearchDriver($this->esClient, $this->esIndexName);
    }

    protected function tearDown(): void
    {
        try {
            $this->esClient->delete([
                'index' => $this->esIndexName,
                'id' => self::TEST_ID,
            ]);
        } catch (\Elastic\Elasticsearch\Exception\ClientResponseException) {
            // Expected if document already deleted
        }
    }

    public function testFindByIdExisting(): void
    {
        $result = $this->driver->findById(self::TEST_ID);

        self::assertNotEmpty($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame(self::TEST_ID, $result['id']);
    }

    public function testFindByIdNotFound(): void
    {
        $this->expectException(ProductNotFoundException::class);
        $this->driver->findById('00000000-0000-0000-0000-000000000000');
    }

    public function testSearchByName(): void
    {
        $result = $this->driver->search([
            'index' => $this->esIndexName,
            'body' => [
                'query' => [
                    'match' => [
                        'name' => 'ES Driver Test Product',
                    ],
                ],
            ],
        ]);

        self::assertNotEmpty($result);
        self::assertArrayHasKey('hits', $result);
    }

    public function testFindAllIds(): void
    {
        $result = $this->driver->search([
            'index' => $this->esIndexName,
            'body' => [
                'query' => ['match_all' => new \stdClass()],
                '_source' => false,
                'size' => 1000,
            ],
        ]);

        self::assertNotEmpty($result);
        self::assertArrayHasKey('hits', $result);
        $hits = $result['hits']['hits'] ?? [];
        self::assertIsArray($hits);
        self::assertGreaterThanOrEqual(1, \count($hits));
    }
}
