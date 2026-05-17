<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Counter;

use App\Domain\ValueObject\ProductId;
use App\Infrastructure\Counter\FilesystemCounter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Integration tests for FilesystemCounter with real FilesystemAdapter.
 */
final class FilesystemCounterTest extends TestCase
{
    private FilesystemCounter $counter;
    private string $tempDir;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir().'/logio_counter_test_'.\uniqid();
        \mkdir($this->tempDir);

        $adapter = new FilesystemAdapter('counter', 0, $this->tempDir);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->counter = new FilesystemCounter($adapter, $this->logger);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testIncrementFromZero(): void
    {
        $id = ProductId::fromString('550e8400-e29b-41d4-a716-446655440031');

        $this->counter->increment($id);

        self::assertSame(1, $this->counter->getCount($id));
    }

    public function testMultipleIncrementsAccumulate(): void
    {
        $id = ProductId::fromString('550e8400-e29b-41d4-a716-446655440032');

        for ($i = 0; $i < 5; ++$i) {
            $this->counter->increment($id);
        }

        self::assertSame(5, $this->counter->getCount($id));
    }

    public function testGetCountReturnsZeroForNeverIncrementedProduct(): void
    {
        $id = ProductId::fromString('550e8400-e29b-41d4-a716-446655440033');

        self::assertSame(0, $this->counter->getCount($id));
    }

    public function testDifferentProductsHaveIndependentCounters(): void
    {
        $id1 = ProductId::fromString('550e8400-e29b-41d4-a716-446655440034');
        $id2 = ProductId::fromString('550e8400-e29b-41d4-a716-446655440035');

        for ($i = 0; $i < 3; ++$i) {
            $this->counter->increment($id1);
        }
        for ($i = 0; $i < 7; ++$i) {
            $this->counter->increment($id2);
        }

        self::assertSame(3, $this->counter->getCount($id1));
        self::assertSame(7, $this->counter->getCount($id2));
    }
}
