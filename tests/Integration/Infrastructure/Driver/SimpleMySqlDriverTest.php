<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Driver;

use App\Domain\Exception\ProductNotFoundException;
use App\Infrastructure\Driver\SimpleMySqlDriver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for SimpleMySqlDriver against real MySQL (Docker).
 */
final class SimpleMySqlDriverTest extends KernelTestCase
{
    private \PDO $pdo;
    private SimpleMySqlDriver $driver;

    private const TEST_ID = '550e8400-e29b-41d4-a716-446655440100';

    protected function setUp(): void
    {
        $pdo = static::getContainer()->get(\PDO::class);
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('PDO service not found');
        }
        $this->pdo = $pdo;

        $stmt = $this->pdo->prepare(
            'REPLACE INTO products (id, name, price, description) VALUES (:id, :name, :price, :description)',
        );
        $stmt->execute([
            ':id' => self::TEST_ID,
            ':name' => 'Driver Test Product',
            ':price' => 2999,
            ':description' => 'Driver test description',
        ]);

        $this->driver = new SimpleMySqlDriver($this->pdo);
    }

    protected function tearDown(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => self::TEST_ID]);
    }

    public function testFindByIdExisting(): void
    {
        $result = $this->driver->findProduct(self::TEST_ID);

        self::assertNotEmpty($result);
        self::assertArrayHasKey('id', $result);
        self::assertArrayHasKey('name', $result);
        self::assertArrayHasKey('price', $result);
        self::assertArrayHasKey('description', $result);
        self::assertSame(self::TEST_ID, $result['id']);
    }

    public function testFindByIdNotFound(): void
    {
        $this->expectException(ProductNotFoundException::class);
        $this->driver->findProduct('00000000-0000-0000-0000-000000000000');
    }

    public function testFindAllIds(): void
    {
        $ids = $this->driver->findAllIds(1000);

        self::assertNotEmpty($ids);
        self::assertGreaterThanOrEqual(1, \count($ids));
        self::assertContains(self::TEST_ID, $ids);
    }
}
