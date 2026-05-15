<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Price;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Price value object.
 */
final class PriceTest extends TestCase
{
    public function testPriceCreation(): void
    {
        $price = Price::of('10050');
        self::assertSame('10050', $price->amount());
    }

    public function testPriceCurrency(): void
    {
        $price = Price::of('10050');
        self::assertSame('CZK', $price->currency());
    }

    public function testPriceFormatted(): void
    {
        $price = Price::of('10050');
        self::assertStringContainsString('Kč', $price->formatted());
    }
}
