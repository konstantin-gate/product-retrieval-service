<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Seeder;

use App\Infrastructure\Seeder\ProductSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Tests for ProductSeeder.
 */
final class ProductSeederTest extends TestCase
{
    private ProductSeeder $seeder;

    protected function setUp(): void
    {
        $this->seeder = new ProductSeeder();
    }

    public function testGenerateReturnsCorrectCount(): void
    {
        /** @var int<1, max> $count */
        $count = 10;
        $products = $this->seeder->generate($count);

        self::assertCount(10, $products);
    }

    public function testGenerateSingleProduct(): void
    {
        /** @var int<1, max> $count */
        $count = 1;
        $products = $this->seeder->generate($count);

        self::assertCount(1, $products);
    }

    #[DataProvider('provideProductCounts')]
    /**
     * @param int<1, max> $count
     */
    public function testGeneratedProductsHaveValidUuids(int $count): void
    {
        /** @var int<1, max> $count */
        $products = $this->seeder->generate($count);

        foreach ($products as $product) {
            self::assertTrue(Uuid::isValid($product->id->value()));
        }
    }

    #[DataProvider('provideProductCounts')]
    /**
     * @param int<1, max> $count
     */
    public function testGeneratedProductsHaveNonEmptyNames(int $count): void
    {
        /** @var int<1, max> $count */
        $products = $this->seeder->generate($count);

        foreach ($products as $product) {
            self::assertNotSame('', $product->name);
        }
    }

    #[DataProvider('provideProductCounts')]
    /**
     * @param int<1, max> $count
     */
    public function testGeneratedProductsHavePositivePrice(int $count): void
    {
        /** @var int<1, max> $count */
        $products = $this->seeder->generate($count);

        foreach ($products as $product) {
            self::assertGreaterThan(0, (int) $product->price->amount());
        }
    }

    #[DataProvider('provideProductCounts')]
    /**
     * @param int<1, max> $count
     */
    public function testGeneratedProductsHaveNonEmptyDescriptions(int $count): void
    {
        /** @var int<1, max> $count */
        $products = $this->seeder->generate($count);

        foreach ($products as $product) {
            self::assertNotSame('', $product->description);
        }
    }

    public function testGeneratedProductsHaveUniqueIds(): void
    {
        /** @var int<1, max> $count */
        $count = 50;
        $products = $this->seeder->generate($count);
        $ids = \array_map(static fn ($p): string => $p->id->value(), $products);

        self::assertSame(\array_unique($ids), $ids);
    }

    /**
     * @return list<list<int<1, max>>>
     */
    public static function provideProductCounts(): array
    {
        return [[1], [5], [20]];
    }
}
