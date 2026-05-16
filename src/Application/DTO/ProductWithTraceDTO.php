<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\DTO\ProductDTO;

/**
 * Carries product data along with execution trace metadata.
 *
 * Unlike ProductDTO (Domain), this DTO lives in the Application layer
 * because trace metadata (cache hit, source, execution log) is an
 * application-level concern, not a domain invariant.
 *
 * TTFB is NOT included here — it is an HTTP-layer metric measured
 * in the controller and passed to Twig as a separate variable.
 */
final readonly class ProductWithTraceDTO
{
    /**
     * @param ProductDTO   $product         The retrieved product
     * @param bool         $cacheHit        Whether the product was served from cache
     * @param string       $source          Data source name (e.g. "elasticsearch", "mysql")
     * @param list<string> $executionLog    Ordered list of execution step descriptions
     * @param int          $optimisticCount Optimistic view count for async mode display
     */
    public function __construct(
        public ProductDTO $product,
        public bool $cacheHit,
        public string $source,
        public array $executionLog,
        public int $optimisticCount,
    ) {
    }
}
