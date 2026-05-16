<?php

declare(strict_types=1);

namespace App\Domain\Contract;

/**
 * Contract for seeding product data into storage backends.
 */
interface SeederInterface
{
    /**
     * Seeds the specified number of products into MySQL and ElasticSearch.
     *
     * @param int<1, max> $count Number of products to generate and persist
     */
    public function seed(int $count): void;
}
