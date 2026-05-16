<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Cache;

use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\Price;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Cache\RedisCacheAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

interface RedisCacheSerializerMockInterface extends NormalizerInterface, DenormalizerInterface
{
}

/**
 * Integration tests for RedisCacheAdapter with real Redis (Docker).
 */
final class RedisCacheAdapterTest extends TestCase
{
    private RedisCacheAdapter $cache;
    private RedisCacheSerializerMockInterface&MockObject $serializer;

    protected function setUp(): void
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('The redis extension is not loaded.');
        }

        $redis = new \Redis();
        $redis->connect('redis', 6379);
        $redis->select(1);
        $redis->flushDB();

        $fsAdapter = new RedisAdapter($redis, namespace: 'test');
        $this->serializer = $this->createMock(RedisCacheSerializerMockInterface::class);
        $this->cache = new RedisCacheAdapter($fsAdapter, $this->serializer);
    }

    private function createDto(): ProductDTO
    {
        return new ProductDTO(
            ProductId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            'Test Product',
            Price::of('10000'),
            'Test Description',
        );
    }

    public function testSetAndGet(): void
    {
        $dto = $this->createDto();
        $normalized = [
            'id' => $dto->id->value(),
            'name' => $dto->name,
            'price' => $dto->price->amount(),
            'description' => $dto->description,
        ];

        $this->serializer->method('normalize')->with($dto, 'json')->willReturn($normalized);
        $this->serializer->method('denormalize')->with($normalized, ProductDTO::class)->willReturn($dto);

        $this->cache->set('key', $dto);
        $result = $this->cache->get('key');

        self::assertNotNull($result);
        self::assertSame($dto->id->value(), $result->id->value());
        self::assertSame($dto->name, $result->name);
    }

    public function testGetMissReturnsNull(): void
    {
        self::assertNull($this->cache->get('nonexistent'));
    }

    public function testSetWithTtlExpires(): void
    {
        $dto = $this->createDto();
        $normalized = [
            'id' => $dto->id->value(),
            'name' => $dto->name,
            'price' => $dto->price->amount(),
            'description' => $dto->description,
        ];

        $this->serializer->method('normalize')->willReturn($normalized);
        $this->serializer->method('denormalize')->willReturn($dto);

        $this->cache->set('key', $dto, ttl: 1);
        self::assertNotNull($this->cache->get('key'));

        \sleep(2);

        self::assertNull($this->cache->get('key'));
    }

    public function testDelete(): void
    {
        $dto = $this->createDto();
        $normalized = [
            'id' => $dto->id->value(),
            'name' => $dto->name,
            'price' => $dto->price->amount(),
            'description' => $dto->description,
        ];

        $this->serializer->method('normalize')->willReturn($normalized);
        $this->serializer->method('denormalize')->willReturn($dto);

        $this->cache->set('key', $dto);
        $this->cache->delete('key');

        self::assertNull($this->cache->get('key'));
    }
}
