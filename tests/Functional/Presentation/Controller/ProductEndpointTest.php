<?php

declare(strict_types=1);

namespace App\Tests\Functional\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductEndpointTest extends WebTestCase
{
    public function testDetailSuccess(): void
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

    public function testDetailInvalidId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/product/invalid-uuid', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Invalid product ID', $data['error']);
    }

    public function testDetailNotFound(): void
    {
        $client = static::createClient();
        $id = '00000000-0000-0000-0000-000000000000';
        $client->request('GET', '/product/'.$id, [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Product not found', $data['error']);
    }

    public function testCounter(): void
    {
        $client = static::createClient();
        $id = '550e8400-e29b-41d4-a716-446655442222';

        // Increment once
        $client->request('GET', '/product/'.$id.'/counter');
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('count', $data);
    }
}
