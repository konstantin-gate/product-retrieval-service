<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Thrown when a message queue operation fails.
 */
final class QueueException extends \RuntimeException
{
}
