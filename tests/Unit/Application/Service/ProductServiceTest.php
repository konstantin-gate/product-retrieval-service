<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Service\ProductService;
use App\Domain\Contract\CacheInterface;
use App\Domain\Contract\ConfigInterface;
use App\Domain\Contract\CounterInterface;
use App\Domain\Contract\ProductSourceInterface;
use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\Price;
use App\Domain\ValueObject\ProductId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProductServiceTest extends TestCase
{
    private ProductSourceInterface&MockObject $source;
    private CacheInterface&MockObject $cache;
    private CounterInterface&MockObject $counter;
    private ConfigInterface&MockObject $config;
    private ProductService $service;

    protected function setUp(): void
    {
        $this->source = $this->createMock(ProductSourceInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->counter = $this->createMock(CounterInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->service = new ProductService($this->source, $this->cache, $this->counter, $this->config);
    }

    public function testGetProductCacheHit(): void
    {
        $id = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $product = new ProductDTO($id, 'Test', Price::of('1000'), 'Desc');

        $this->counter->expects($this->once())->method('increment')->with($id);
        $this->cache->expects($this->once())->method('get')->with('product_'.$id->value())->willReturn($product);
        $this->source->expects($this->never())->method('findById');

        $result = $this->service->getProduct($id);

        self::assertSame($product, $result);
    }

    public function testGetProductCacheMiss(): void
    {
        $id = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $product = new ProductDTO($id, 'Test', Price::of('1000'), 'Desc');

        $this->counter->expects($this->once())->method('increment')->with($id);
        $this->cache->expects($this->once())->method('get')->with('product_'.$id->value())->willReturn(null);
        $this->source->expects($this->once())->method('findById')->with($id)->willReturn($product);
        $this->cache->expects($this->once())->method('set')->with('product_'.$id->value(), $product);

        $result = $this->service->getProduct($id);

        self::assertSame($product, $result);
    }

    public function testGetCount(): void
    {
        $id = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $this->counter->expects($this->once())->method('getCount')->with($id)->willReturn(42);

        self::assertSame(42, $this->service->getCount($id));
    }

    public function testGetSampleProductIds(): void
    {
        $ids = ['id1', 'id2'];
        $this->source->expects($this->once())->method('findSampleIds')->with(5)->willReturn($ids);

        self::assertSame($ids, $this->service->getSampleProductIds(5));
    }
}
