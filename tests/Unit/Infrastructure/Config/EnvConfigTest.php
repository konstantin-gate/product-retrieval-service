<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Config;

use App\Infrastructure\Config\EnvConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Tests for EnvConfig.
 */
final class EnvConfigTest extends TestCase
{
    private ParameterBagInterface&MockObject $parameterBag;
    private EnvConfig $config;

    protected function setUp(): void
    {
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->config = new EnvConfig($this->parameterBag);
    }

    public function testGetStringReturnsValue(): void
    {
        $this->parameterBag->method('get')->with('key')->willReturn('value');

        self::assertSame('value', $this->config->getString('key'));
    }

    public function testGetIntReturnsValue(): void
    {
        $this->parameterBag->method('get')->with('key')->willReturn(42);

        self::assertSame(42, $this->config->getInt('key'));
    }

    public function testGetBoolReturnsValue(): void
    {
        $this->parameterBag->method('get')->with('key')->willReturn(true);

        self::assertTrue($this->config->getBool('key'));
    }

    public function testGetDataSourceReturnsMysql(): void
    {
        $this->parameterBag->method('get')->with('app.active_product_source')->willReturn('mysql');

        self::assertSame('mysql', $this->config->getDataSource());
    }

    public function testGetDataSourceReturnsElasticsearch(): void
    {
        $this->parameterBag->method('get')->with('app.active_product_source')->willReturn('elasticsearch');

        self::assertSame('elasticsearch', $this->config->getDataSource());
    }

    public function testGetCacheDriverReturnsFile(): void
    {
        $this->parameterBag->method('get')->with('app.active_cache_driver')->willReturn('file');

        self::assertSame('file', $this->config->getCacheDriver());
    }

    public function testGetCounterModeReturnsAsync(): void
    {
        $this->parameterBag->method('get')->with('app.active_counter_mode')->willReturn('async');

        self::assertSame('async', $this->config->getCounterMode());
    }

    public function testGetBoolReturnsTrue(): void
    {
        $this->parameterBag->method('get')->with('SOME_BOOL')->willReturn('1');

        self::assertTrue($this->config->getBool('SOME_BOOL'));
    }

    public function testGetIntReturnsInt(): void
    {
        $this->parameterBag->method('get')->with('CACHE_TTL')->willReturn('3600');

        self::assertSame(3600, $this->config->getInt('CACHE_TTL'));
    }
}
