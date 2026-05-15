<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Seeder;

use App\Infrastructure\Seeder\ProductSeeder;
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
        $products = $this->seeder->generate(10);

        self::assertCount(10, $products);
    }

    public function testGenerateSingleProduct(): void
    {
        $products = $this->seeder->generate(1);

        self::assertCount(1, $products);
    }

    /**
     * @dataProvider provideProductCounts
     */
    public function testGeneratedProductsHaveValidUuids(int $count): void
    {
        $products = $this->seeder->generate($count);

        foreach ($products as $product) {
            self::assertTrue(Uuid::isValid($product->id->value()));
        }
    }

    /**
     * @dataProvider provideProductCounts
     */
    public function testGeneratedProductsHaveNonEmptyNames(int $count): void
    {
        $products = $this->seeder->generate($count);

        foreach ($products as $product) {
            self::assertNotSame('', $product->name);
        }
    }

    /**
     * @dataProvider provideProductCounts
     */
    public function testGeneratedProductsHavePositivePrice(int $count): void
    {
        $products = $this->seeder->generate($count);

        foreach ($products as $product) {
            self::assertGreaterThan(0, (int) $product->price->amount());
        }
    }

    /**
     * @dataProvider provideProductCounts
     */
    public function testGeneratedProductsHaveNonEmptyDescriptions(int $count): void
    {
        $products = $this->seeder->generate($count);

        foreach ($products as $product) {
            self::assertNotSame('', $product->description);
        }
    }

    public function testGeneratedProductsHaveUniqueIds(): void
    {
        $products = $this->seeder->generate(50);
        $ids = \array_map(static fn ($p): string => $p->id->value(), $products);

        self::assertSame(\array_unique($ids), $ids);
    }

    /**
     * @return list<list<int>>
     */
    public static function provideProductCounts(): array
    {
        return [[1], [5], [20]];
    }
}
