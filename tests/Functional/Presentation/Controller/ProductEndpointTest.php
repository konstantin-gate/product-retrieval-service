<?php

declare(strict_types=1);

namespace App\Tests\Functional\Presentation\Controller;

use App\Domain\Contract\ConfigInterface;
use Elastic\Elasticsearch\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductEndpointTest extends WebTestCase
{
    public function testGetProductExistingReturns200(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $config = $container->get(ConfigInterface::class);
        if (!$config instanceof ConfigInterface) {
            throw new \RuntimeException('ConfigInterface not found');
        }
        $esIndexName = $config->getEsIndexName();

        $pdo = $container->get(\PDO::class);
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('PDO not found');
        }

        $esClient = $container->get(Client::class);
        if (!$esClient instanceof Client) {
            throw new \RuntimeException('ElasticSearch client not found');
        }

        $id = '550e8400-e29b-41d4-a716-446655441111';
        $stmt = $pdo->prepare('REPLACE INTO products (id, name, price, description) VALUES (:id, :name, :price, :description)');
        $stmt->execute([
            ':id' => $id,
            ':name' => 'Test Product',
            ':price' => 1999,
            ':description' => 'Test Description',
        ]);

        $esClient->index([
            'index' => $esIndexName,
            'id' => $id,
            'body' => [
                'id' => $id,
                'name' => 'Test Product',
                'price' => 1999,
                'description' => 'Test Description',
            ],
            'refresh' => 'true',
        ]);

        $client->request('GET', '/product/'.$id);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $content = $client->getResponse()->getContent();
        if (false === $content) {
            throw new \RuntimeException('Response content is false');
        }
        /** @var array<string, mixed> $data */
        $data = json_decode($content, true);
        self::assertSame($id, $data['id']);
        self::assertSame('Test Product', $data['name']);
        self::assertSame(1999, $data['price']);
    }

    public function testGetProductNotFoundReturns404(): void
    {
        $client = static::createClient();
        $id = '00000000-0000-0000-0000-000000000000';
        $client->request('GET', '/product/'.$id, [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(404);
        $content = $client->getResponse()->getContent();
        if (false === $content) {
            throw new \RuntimeException('Response content is false');
        }
        /** @var array<string, mixed> $data */
        $data = json_decode($content, true);
        self::assertSame('Product not found', $data['error']);
    }

    public function testGetProductInvalidIdReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/product/invalid-uuid', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(400);
        $content = $client->getResponse()->getContent();
        if (false === $content) {
            throw new \RuntimeException('Response content is false');
        }
        /** @var array<string, mixed> $data */
        $data = json_decode($content, true);
        self::assertSame('Invalid product ID', $data['error']);
    }

    public function testCounterIncrementsOnRepeatedRequests(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $config = $container->get(ConfigInterface::class);
        if (!$config instanceof ConfigInterface) {
            throw new \RuntimeException('ConfigInterface not found');
        }
        $esIndexName = $config->getEsIndexName();

        $pdo = $container->get(\PDO::class);
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('PDO not found');
        }

        $esClient = $container->get(Client::class);
        if (!$esClient instanceof Client) {
            throw new \RuntimeException('ElasticSearch client not found');
        }

        $id = '550e8400-e29b-41d4-a716-446655442222';

        $redis = $container->get(\Redis::class);
        if (!$redis instanceof \Redis) {
            throw new \RuntimeException('Redis not found');
        }
        $redis->del('counter:'.$id);

        $stmt = $pdo->prepare('REPLACE INTO products (id, name, price, description) VALUES (:id, :name, :price, :description)');
        $stmt->execute([
            ':id' => $id,
            ':name' => 'Counter Test Product',
            ':price' => 999,
            ':description' => 'Counter Description',
        ]);

        $esClient->index([
            'index' => $esIndexName,
            'id' => $id,
            'body' => [
                'id' => $id,
                'name' => 'Counter Test Product',
                'price' => 999,
                'description' => 'Counter Description',
            ],
            'refresh' => 'true',
        ]);

        // Counter starts at 0 after cleanup
        $client->request('GET', '/product/'.$id.'/counter');
        self::assertResponseIsSuccessful();
        $content1 = $client->getResponse()->getContent();
        if (false === $content1) {
            throw new \RuntimeException('Response content is false');
        }
        /** @var array<string, mixed> $data1 */
        $data1 = json_decode($content1, true);
        self::assertSame(0, $data1['count']);

        // Make product requests to increment counter (sync mode — immediate)
        $client->request('GET', '/product/'.$id);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/product/'.$id);
        self::assertResponseIsSuccessful();

        // Counter endpoint should reflect exactly 2 increments
        $client->request('GET', '/product/'.$id.'/counter');
        self::assertResponseIsSuccessful();
        $content2 = $client->getResponse()->getContent();
        if (false === $content2) {
            throw new \RuntimeException('Response content is false');
        }
        /** @var array<string, mixed> $data2 */
        $data2 = json_decode($content2, true);
        self::assertSame(2, $data2['count']);
    }

    public function testSerializationStructure(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $config = $container->get(ConfigInterface::class);
        if (!$config instanceof ConfigInterface) {
            throw new \RuntimeException('ConfigInterface not found');
        }
        $esIndexName = $config->getEsIndexName();

        $pdo = $container->get(\PDO::class);
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('PDO not found');
        }

        $esClient = $container->get(Client::class);
        if (!$esClient instanceof Client) {
            throw new \RuntimeException('ElasticSearch client not found');
        }

        $id = '550e8400-e29b-41d4-a716-446655443333';
        $stmt = $pdo->prepare('REPLACE INTO products (id, name, price, description) VALUES (:id, :name, :price, :description)');
        $stmt->execute([
            ':id' => $id,
            ':name' => 'Serialization Test',
            ':price' => 2999,
            ':description' => 'Serialization Description',
        ]);

        $esClient->index([
            'index' => $esIndexName,
            'id' => $id,
            'body' => [
                'id' => $id,
                'name' => 'Serialization Test',
                'price' => 2999,
                'description' => 'Serialization Description',
            ],
            'refresh' => 'true',
        ]);

        $client->request('GET', '/product/'.$id);

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        if (false === $content) {
            throw new \RuntimeException('Response content is false');
        }
        /** @var array<string, mixed> $data */
        $data = json_decode($content, true);

        self::assertIsString($data['id']);
        self::assertIsInt($data['price']);
        self::assertIsString($data['name']);
        self::assertIsString($data['description']);
    }
}
