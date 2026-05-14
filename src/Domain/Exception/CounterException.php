<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Thrown when a counter operation fails (increment or get count).
 */
final class CounterException extends \RuntimeException
{
}
