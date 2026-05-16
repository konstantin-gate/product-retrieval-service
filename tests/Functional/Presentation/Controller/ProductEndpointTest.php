<?php

declare(strict_types=1);

namespace App\Tests\Functional\Presentation\Controller;

use App\Domain\Contract\SyncCounterInterface;
use App\Domain\ValueObject\ProductId;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ProductEndpointTest extends WebTestCase
{
    public function testGetProductExistingReturns200(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $pdo = $container->get(\PDO::class);
        $esClient = $container->get(\Elastic\Elasticsearch\Client::class);

        $id = '550e8400-e29b-41d4-a716-446655441111';
        $stmt = $pdo->prepare('REPLACE INTO products (id, name, price, description) VALUES (:id, :name, :price, :description)');
        $stmt->execute([
            ':id' => $id,
            ':name' => 'Test Product',
            ':price' => 1999,
            ':description' => 'Test Description',
        ]);

        $esClient->index([
            'index' => 'products',
            'id' => $id,
            'body' => [
                'id' => $id,
                'name' => 'Test Product',
                'price' => 1999,
                'description' => 'Test Description',
            ],
            'refresh' => true,
        ]);

        $client->request('GET', '/product/'.$id);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
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
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Product not found', $data['error']);
    }

    public function testGetProductInvalidIdReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/product/invalid-uuid', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Invalid product ID', $data['error']);
    }

    public function testCounterIncrementsOnRepeatedRequests(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $pdo = $container->get(\PDO::class);
        $esClient = $container->get(\Elastic\Elasticsearch\Client::class);

        $id = '550e8400-e29b-41d4-a716-446655442222';

        $stmt = $pdo->prepare('REPLACE INTO products (id, name, price, description) VALUES (:id, :name, :price, :description)');
        $stmt->execute([
            ':id' => $id,
            ':name' => 'Counter Test Product',
            ':price' => 999,
            ':description' => 'Counter Description',
        ]);

        $esClient->index([
            'index' => 'products',
            'id' => $id,
            'body' => [
                'id' => $id,
                'name' => 'Counter Test Product',
                'price' => 999,
                'description' => 'Counter Description',
            ],
            'refresh' => true,
        ]);

        $client->request('GET', '/product/'.$id.'/counter');
        self::assertResponseIsSuccessful();
        $data1 = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('count', $data1);
        $initialCount = $data1['count'];

        // Make product requests to increment counter
        $client->request('GET', '/product/'.$id);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/product/'.$id);
        self::assertResponseIsSuccessful();

        // Consume async messages to increment counter
        $kernel = static::getContainer()->get('kernel');
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'messenger:consume',
            'receivers' => ['async'],
            '--limit' => 10,
            '--time-limit' => 1,
        ]);
        $output = new BufferedOutput();
        $application->run($input, $output);

        // Check sync counter directly (async counter delegates to sync counter)
        $syncCounter = static::getContainer()->get(SyncCounterInterface::class);
        $syncCount = $syncCounter->getCount(ProductId::fromString($id));
        self::assertGreaterThanOrEqual(2, $syncCount);

        // HTTP counter endpoint should also reflect the value
        $client->request('GET', '/product/'.$id.'/counter');
        self::assertResponseIsSuccessful();
        $data2 = json_decode($client->getResponse()->getContent(), true);
        self::assertGreaterThanOrEqual($initialCount + $syncCount, $data2['count']);
    }

    public function testSerializationStructure(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $pdo = $container->get(\PDO::class);
        $esClient = $container->get(\Elastic\Elasticsearch\Client::class);

        $id = '550e8400-e29b-41d4-a716-446655443333';
        $stmt = $pdo->prepare('REPLACE INTO products (id, name, price, description) VALUES (:id, :name, :price, :description)');
        $stmt->execute([
            ':id' => $id,
            ':name' => 'Serialization Test',
            ':price' => 2999,
            ':description' => 'Serialization Description',
        ]);

        $esClient->index([
            'index' => 'products',
            'id' => $id,
            'body' => [
                'id' => $id,
                'name' => 'Serialization Test',
                'price' => 2999,
                'description' => 'Serialization Description',
            ],
            'refresh' => true,
        ]);

        $client->request('GET', '/product/'.$id);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsString($data['id']);
        self::assertIsInt($data['price']);
        self::assertIsString($data['name']);
        self::assertIsString($data['description']);
    }
}
