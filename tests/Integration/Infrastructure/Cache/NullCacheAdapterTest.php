<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Cache;

use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\Price;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Cache\NullCacheAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for NullCacheAdapter.
 */
final class NullCacheAdapterTest extends TestCase
{
    private NullCacheAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new NullCacheAdapter();
    }

    public function testSetDoesNothing(): void
    {
        $dto = new ProductDTO(
            ProductId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            'Test',
            Price::of('100'),
            'Desc',
        );

        $this->cache->set('key', $dto);

        self::assertNull($this->cache->get('key'));
    }

    public function testGetAlwaysReturnsNull(): void
    {
        self::assertNull($this->cache->get('any_key'));
    }
}
