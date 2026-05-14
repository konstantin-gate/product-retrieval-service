<?php

declare(strict_types=1);

namespace App\Domain\Contract;

/**
 * Contract for MySQL driver operations.
 *
 * Abstracts PDO behind a domain port.
 */
interface IMysqlDriver
{
    /**
     * Finds a product row by ID in MySQL.
     *
     * @param string $id Product identifier
     *
     * @return array<string, mixed> Raw product row
     *
     * @throws \RuntimeException if the product is not found or database is unavailable
     */
    public function findProduct(string $id): array;
}
