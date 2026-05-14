<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Thrown when a product cannot be found in the underlying data source.
 *
 * This exception separates the "not found" case from general source
 * unavailability, allowing callers to handle missing products differently
 * from connection or infrastructure failures.
 */
final class ProductNotFoundException extends \RuntimeException
{
}
