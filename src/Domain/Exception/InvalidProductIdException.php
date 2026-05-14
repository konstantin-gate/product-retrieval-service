<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Thrown when a product ID fails UUID validation.
 */
final class InvalidProductIdException extends \InvalidArgumentException
{
}
