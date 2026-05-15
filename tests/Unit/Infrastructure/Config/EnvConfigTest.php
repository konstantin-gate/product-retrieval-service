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

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->parameterBag->method('has')->with('key')->willReturn(true);

        self::assertTrue($this->config->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->parameterBag->method('has')->with('key')->willReturn(false);

        self::assertFalse($this->config->has('key'));
    }

    public function testGetDataSourceDelegatesToGetString(): void
    {
        $this->parameterBag->method('get')->with('app.active_product_source')->willReturn('mysql');

        self::assertSame('mysql', $this->config->getDataSource());
    }

    public function testGetCacheDriverDelegatesToGetString(): void
    {
        $this->parameterBag->method('get')->with('app.active_cache_driver')->willReturn('redis');

        self::assertSame('redis', $this->config->getCacheDriver());
    }

    public function testGetCounterModeDelegatesToGetString(): void
    {
        $this->parameterBag->method('get')->with('app.active_counter_mode')->willReturn('async');

        self::assertSame('async', $this->config->getCounterMode());
    }
}
