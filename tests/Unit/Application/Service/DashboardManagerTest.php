<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Service\DashboardManager;
use App\Domain\Contract\ConfigInterface;
use App\Domain\Contract\EnvFileWriterInterface;
use App\Domain\Contract\HealthCheckInterface;
use App\Domain\Contract\ProductSourceInterface;
use App\Domain\Contract\SeederInterface;
use App\Infrastructure\Adapter\EnvFileWriterAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit tests for DashboardManager.
 */
final class DashboardManagerTest extends TestCase
{
    private string $projectDir;
    private Filesystem $filesystem;
    private EnvFileWriterInterface $envFileWriter;
    private ConfigInterface&MockObject $config;
    private ProductSourceInterface&MockObject $source;
    private HealthCheckInterface&MockObject $mysqlHealth;
    private HealthCheckInterface&MockObject $elasticSearchHealth;
    private HealthCheckInterface&MockObject $redisHealth;
    private SeederInterface&MockObject $seeder;
    private DashboardManager $manager;

    protected function setUp(): void
    {
        $this->projectDir = \sys_get_temp_dir().'/logio_test_'.\uniqid();
        \mkdir($this->projectDir);
        $this->filesystem = new Filesystem();
        $this->envFileWriter = new EnvFileWriterAdapter($this->filesystem);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->source = $this->createMock(ProductSourceInterface::class);
        $this->mysqlHealth = $this->createMock(HealthCheckInterface::class);
        $this->elasticSearchHealth = $this->createMock(HealthCheckInterface::class);
        $this->redisHealth = $this->createMock(HealthCheckInterface::class);
        $this->seeder = $this->createMock(SeederInterface::class);

        $this->manager = new DashboardManager(
            $this->projectDir,
            $this->envFileWriter,
            $this->config,
            $this->source,
            $this->mysqlHealth,
            $this->elasticSearchHealth,
            $this->redisHealth,
            $this->seeder,
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testSetToggleCreatesNewFile(): void
    {
        $envLocalPath = $this->projectDir.'/.env.local';

        $this->manager->setToggle('TEST_KEY', 'test_value');

        self::assertFileExists($envLocalPath);
        self::assertStringContainsString('TEST_KEY=test_value', $this->filesystem->readFile($envLocalPath));
    }

    public function testSetToggleUpdatesExistingKey(): void
    {
        $envLocalPath = $this->projectDir.'/.env.local';
        $this->filesystem->dumpFile($envLocalPath, "TEST_KEY=old_value\nOTHER_KEY=other\n");

        $this->manager->setToggle('TEST_KEY', 'new_value');

        $content = $this->filesystem->readFile($envLocalPath);
        self::assertStringContainsString('TEST_KEY=new_value', $content);
        self::assertStringContainsString('OTHER_KEY=other', $content);
        self::assertStringNotContainsString('TEST_KEY=old_value', $content);
    }

    public function testSetToggleAppendsNewKey(): void
    {
        $envLocalPath = $this->projectDir.'/.env.local';
        $this->filesystem->dumpFile($envLocalPath, "EXISTING_KEY=existing\n");

        $this->manager->setToggle('NEW_KEY', 'new_value');

        $content = $this->filesystem->readFile($envLocalPath);
        self::assertStringContainsString('EXISTING_KEY=existing', $content);
        self::assertStringContainsString('NEW_KEY=new_value', $content);
    }

    public function testGetCurrentConfig(): void
    {
        $this->config->method('getDataSource')->willReturn('mysql');
        $this->config->method('getCacheDriver')->willReturn('redis');
        $this->config->method('getCounterMode')->willReturn('async');

        $config = $this->manager->getCurrentConfig();

        self::assertSame([
            'source' => 'mysql',
            'cache' => 'redis',
            'counter' => 'async',
        ], $config);
    }

    public function testGetHealthStatus(): void
    {
        $this->mysqlHealth->method('isHealthy')->willReturn(true);
        $this->elasticSearchHealth->method('isHealthy')->willReturn(false);
        $this->redisHealth->method('isHealthy')->willReturn(true);

        $status = $this->manager->getHealthStatus();

        self::assertTrue($status['mysql']);
        self::assertFalse($status['elasticsearch']);
        self::assertTrue($status['redis']);
    }

    public function testGetSampleProductIds(): void
    {
        $ids = ['id1', 'id2'];
        $this->source->expects($this->once())->method('findSampleIds')->with(5)->willReturn($ids);

        self::assertSame($ids, $this->manager->getSampleProductIds(5));
    }
}
