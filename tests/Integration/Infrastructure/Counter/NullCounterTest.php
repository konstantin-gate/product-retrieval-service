<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Counter;

use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Counter\NullCounter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for NullCounter.
 */
final class NullCounterTest extends TestCase
{
    private NullCounter $counter;

    protected function setUp(): void
    {
        $this->counter = new NullCounter();
    }

    public function testIncrementDoesNothing(): void
    {
        $id = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $this->counter->increment($id);

        self::assertSame(0, $this->counter->getCount($id));
    }

    public function testGetCountAlwaysZero(): void
    {
        $id = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame(0, $this->counter->getCount($id));
    }
}
