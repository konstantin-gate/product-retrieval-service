<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Thrown when a product source (MySQL, ElasticSearch) is unreachable or returns an error.
 */
final class SourceUnavailableException extends \RuntimeException
{
}
