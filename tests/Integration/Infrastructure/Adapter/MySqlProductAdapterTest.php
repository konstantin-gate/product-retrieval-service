<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapter;

use App\Domain\Contract\IMysqlDriver;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Adapter\MySqlProductAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for MySqlProductAdapter against real MySQL (Docker).
 */
final class MySqlProductAdapterTest extends KernelTestCase
{
    private \PDO $pdo;
    private MySqlProductAdapter $adapter;

    private const TEST_ID_1 = '550e8400-e29b-41d4-a716-446655440001';
    private const TEST_ID_2 = '550e8400-e29b-41d4-a716-446655440002';
    private const TEST_ID_3 = '550e8400-e29b-41d4-a716-446655440003';
    private const TEST_IDS = [self::TEST_ID_1, self::TEST_ID_2, self::TEST_ID_3];

    protected function setUp(): void
    {
        $container = static::getContainer();

        $pdo = $container->get(\PDO::class);
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('PDO service not found');
        }
        $this->pdo = $pdo;

        $driver = $container->get(IMysqlDriver::class);
        if (!$driver instanceof IMysqlDriver) {
            throw new \RuntimeException('IMysqlDriver service not found');
        }
        $this->adapter = new MySqlProductAdapter($driver);

        // Clean up any previous test data
        $placeholders = \implode(', ', \array_fill(0, \count(self::TEST_IDS), '?'));
        $this->pdo->prepare("DELETE FROM products WHERE id IN ({$placeholders})")
            ->execute(self::TEST_IDS);

        // Insert test data
        $stmt = $this->pdo->prepare(
            'INSERT INTO products (id, name, price, description) VALUES (:id, :name, :price, :description)',
        );
        $stmt->execute([':id' => self::TEST_ID_1, ':name' => 'Test Product', ':price' => 1999, ':description' => 'Test Description']);
        $stmt->execute([':id' => self::TEST_ID_2, ':name' => 'Second Product', ':price' => 2500, ':description' => 'Second Description']);
        $stmt->execute([':id' => self::TEST_ID_3, ':name' => 'Third Product', ':price' => 3000, ':description' => 'Third Description']);
    }

    protected function tearDown(): void
    {
        $placeholders = \implode(', ', \array_fill(0, \count(self::TEST_IDS), '?'));
        $this->pdo->prepare("DELETE FROM products WHERE id IN ({$placeholders})")
            ->execute(self::TEST_IDS);
    }

    public function testFindByIdReturnsProductDtoForExistingProduct(): void
    {
        $product = $this->adapter->findById(ProductId::fromString(self::TEST_ID_1));

        self::assertSame(self::TEST_ID_1, $product->id->value());
        self::assertSame('Test Product', $product->name);
        self::assertSame('1999', $product->price->amount());
        self::assertSame('Test Description', $product->description);
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
