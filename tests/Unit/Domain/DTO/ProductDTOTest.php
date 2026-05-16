<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\DTO;

use App\Domain\DTO\ProductDTO;
use App\Domain\Exception\InvalidProductIdException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProductDTO.
 */
final class ProductDTOTest extends TestCase
{
    public function testFromArrayValid(): void
    {
        $dto = ProductDTO::fromArray([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Test Product',
            'price' => '145000',
            'description' => 'A test product',
        ]);

        self::assertSame('Test Product', $dto->name);
        self::assertSame('A test product', $dto->description);
        self::assertSame('145000', $dto->price->amount());
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $dto->id->value());
    }

    public function testFromArrayMissingKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProductDTO::fromArray([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'price' => '100',
            'description' => 'Desc',
        ]);
    }

    public function testFromArrayInvalidId(): void
    {
        $this->expectException(InvalidProductIdException::class);
        ProductDTO::fromArray([
            'id' => 'not-a-uuid',
            'name' => 'Test',
            'price' => '100',
            'description' => 'Desc',
        ]);
    }

    public function testImmutability(): void
    {
        $reflection = new \ReflectionClass(ProductDTO::class);
        self::assertTrue($reflection->isReadOnly());
    }
}
