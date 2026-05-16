<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Cache;

use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\Price;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Cache\FileCacheAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

interface FileCacheSerializerMockInterface extends NormalizerInterface, DenormalizerInterface
{
}

/**
 * Integration tests for FileCacheAdapter with real FilesystemAdapter.
 */
final class FileCacheAdapterTest extends TestCase
{
    private FileCacheAdapter $cache;
    private string $tempDir;
    private FileCacheSerializerMockInterface&MockObject $serializer;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir().'/logio_cache_test_'.\uniqid();
        \mkdir($this->tempDir);

        $fsAdapter = new FilesystemAdapter('test', 0, $this->tempDir);

        // Mock serializer to handle Price/ProductId normalization correctly
        $this->serializer = $this->createMock(FileCacheSerializerMockInterface::class);

        $this->cache = new FileCacheAdapter($fsAdapter, $this->serializer);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
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

    public function testSetAndGetReturnsSameDto(): void
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

    public function testGetReturnsNullForMissingKey(): void
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

    public function testPermanentCacheDoesNotExpire(): void
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

        $this->cache->set('key', $dto, ttl: null);
        self::assertNotNull($this->cache->get('key'));

        \sleep(2);

        self::assertNotNull($this->cache->get('key'));
    }
}
