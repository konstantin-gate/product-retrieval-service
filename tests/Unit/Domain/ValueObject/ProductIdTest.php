<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidProductIdException;
use App\Domain\ValueObject\ProductId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ProductId value object.
 */
final class ProductIdTest extends TestCase
{
    public function testValidUuid(): void
    {
        $productId = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $productId->value());
    }

    public function testInvalidUuidThrowsException(): void
    {
        $this->expectException(InvalidProductIdException::class);
        ProductId::fromString('invalid-uuid');
    }

    public function testToString(): void
    {
        $productId = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $productId);
    }

    public function testEmptyStringThrowsException(): void
    {
        $this->expectException(InvalidProductIdException::class);
        ProductId::fromString('');
    }

    public function testWhitespaceStringThrowsException(): void
    {
        $this->expectException(InvalidProductIdException::class);
        ProductId::fromString('   ');
    }

    public function testEqualityByValue(): void
    {
        $a = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $b = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame($a->value(), $b->value());
    }
}
