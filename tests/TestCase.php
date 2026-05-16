<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Uid\Uuid;

abstract class TestCase extends BaseTestCase
{
    protected function createValidUuid(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
