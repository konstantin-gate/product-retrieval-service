<?php

declare(strict_types=1);

namespace App\Tests\Concurrent\Infrastructure\Cache;

use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\Price;
use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Cache\FileCacheAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Concurrent tests for FileCacheAdapter verifying atomicity under parallel writes.
 */
final class FileCacheAdapterConcurrentTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        if (!\function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl extension not available');
        }

        $this->tempDir = \sys_get_temp_dir().'/logio_cache_concurrent_'.\uniqid();
        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    private function createSerializer(): NormalizerInterface&DenormalizerInterface
    {
        return new class implements NormalizerInterface, DenormalizerInterface {
            /**
             * @return array<string, mixed>|null
             *
             * @phpstan-ignore-next-line
             */
            public function normalize(mixed $object, ?string $format = null, array $context = []): \ArrayObject|array|string|int|float|bool|null
            {
                if ($object instanceof ProductDTO) {
                    return [
                        'id' => $object->id->value(),
                        'name' => $object->name,
                        'price' => $object->price->amount(),
                        'description' => $object->description,
                    ];
                }

                return null;
            }

            public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
            {
                if (!\is_array($data)) {
                    return null;
                }

                return new ProductDTO(
                    ProductId::fromString((string) $data['id']),
                    (string) $data['name'],
                    Price::of((string) $data['price']),
                    (string) $data['description'],
                );
            }

            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return $data instanceof ProductDTO;
            }

            public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
            {
                return ProductDTO::class === $type;
            }

            public function getSupportedTypes(?string $format): array
            {
                return [ProductDTO::class => true];
            }
        };
    }

    public function testConcurrentWritesDifferentKeys(): void
    {
        $processCount = 5;
        $children = [];

        for ($i = 1; $i <= $processCount; ++$i) {
            $pid = \pcntl_fork();
            if (-1 === $pid) {
                self::fail('Could not fork');
            }
            if (0 === $pid) {
                $fsAdapter = new FilesystemAdapter('test', 0, $this->tempDir);
                $cache = new FileCacheAdapter($fsAdapter, $this->createSerializer(), new NullLogger());
                $dto = new ProductDTO(
                    ProductId::fromString('550e8400-e29b-41d4-a716-446655440000'),
                    'Process '.$i,
                    Price::of('10000'),
                    'Test Description',
                );
                $cache->set('key_'.$i, $dto);
                exit(0);
            }
            $children[] = $pid;
        }

        foreach ($children as $pid) {
            \pcntl_waitpid($pid, $status);
        }

        $fsAdapter = new FilesystemAdapter('test', 0, $this->tempDir);
        $cache = new FileCacheAdapter($fsAdapter, $this->createSerializer(), new NullLogger());

        for ($i = 1; $i <= $processCount; ++$i) {
            self::assertNotNull($cache->get('key_'.$i), 'key_'.$i.' should exist');
        }
    }

    public function testConcurrentWritesSameKey(): void
    {
        $processCount = 5;
        $children = [];

        for ($i = 1; $i <= $processCount; ++$i) {
            $pid = \pcntl_fork();
            if (-1 === $pid) {
                self::fail('Could not fork');
            }
            if (0 === $pid) {
                $fsAdapter = new FilesystemAdapter('test', 0, $this->tempDir);
                $cache = new FileCacheAdapter($fsAdapter, $this->createSerializer(), new NullLogger());
                $dto = new ProductDTO(
                    ProductId::fromString('550e8400-e29b-41d4-a716-446655440000'),
                    'Process '.$i,
                    Price::of('10000'),
                    'Test',
                );
                $cache->set('shared_key', $dto);
                exit(0);
            }
            $children[] = $pid;
        }

        foreach ($children as $pid) {
            \pcntl_waitpid($pid, $status);
        }

        $fsAdapter = new FilesystemAdapter('test', 0, $this->tempDir);
        $cache = new FileCacheAdapter($fsAdapter, $this->createSerializer(), new NullLogger());
        $result = $cache->get('shared_key');

        self::assertNotNull($result);
        self::assertStringStartsWith('Process ', $result->name);
    }
}
