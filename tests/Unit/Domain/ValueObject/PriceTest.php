<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Price;
use Brick\Math\Exception\NumberFormatException;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Price value object.
 */
final class PriceTest extends TestCase
{
    public function testValidPrice(): void
    {
        $price = Price::of('145000');
        self::assertSame('145000', $price->amount());
    }

    public function testZeroPrice(): void
    {
        $price = Price::of('0');
        self::assertSame('0', $price->amount());
    }

    public function testNegativePrice(): void
    {
        $price = Price::of('-100');
        self::assertSame('-100', $price->amount());
    }

    public function testNonNumericThrows(): void
    {
        $this->expectException(NumberFormatException::class);
        Price::of('abc');
    }

    public function testFormattedOutput(): void
    {
        $price = Price::of('145000');
        self::assertSame("1\u{00A0}450,00\u{00A0}Kč", $price->formatted());
    }

    public function testAmountReturnsString(): void
    {
        $price = Price::of('145000');
        self::assertSame('145000', $price->amount());
    }

    public function testCurrencyIsCzk(): void
    {
        $price = Price::of('145000');
        self::assertSame('CZK', $price->currency());
    }

    public function testToMoneyReturnsMoney(): void
    {
        $price = Price::of('145000');
        self::assertInstanceOf(Money::class, $price->toMoney());
    }

    public function testNoFloatError(): void
    {
        $price = Price::of('10');
        self::assertSame('10', $price->amount());
    }

    public function testImmutability(): void
    {
        $reflection = new \ReflectionClass(Price::class);
        self::assertTrue($reflection->isReadOnly());
    }
}
