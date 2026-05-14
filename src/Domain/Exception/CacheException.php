<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Thrown when a cache operation fails (read, write, or delete).
 */
final class CacheException extends \RuntimeException
{
}
