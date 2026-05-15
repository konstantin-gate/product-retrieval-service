<?php

declare(strict_types=1);

namespace App\Infrastructure\Driver;

use App\Domain\Contract\IMysqlDriver;
use App\Domain\Exception\ProductNotFoundException;
use App\Domain\Exception\SourceUnavailableException;

/**
 * MySQL driver implementation using PDO for product data retrieval.
 */
final readonly class SimpleMySqlDriver implements IMysqlDriver
{
    /**
     * @param \PDO $pdo Pre-configured PDO connection
     */
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Finds a product by its ID in MySQL.
     *
     * @param string $id Product identifier
     *
     * @return array<string, mixed> Product row data
     *
     * @throws ProductNotFoundException   if the product does not exist
     * @throws SourceUnavailableException if the database connection fails
     */
    public function findProduct(string $id): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id, name, price, description FROM products WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (false === $row || !\is_array($row)) {
                throw new ProductNotFoundException('Product not found: '.$id);
            }

            return $row;
        } catch (\PDOException $e) {
            throw new SourceUnavailableException('Database error: '.$e->getMessage(), 0, $e);
        }
    }

    public function findAllIds(int $limit): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM products LIMIT :limit');
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            /** @var list<string> $ids */
            $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            return $ids;
        } catch (\PDOException $e) {
            throw new SourceUnavailableException('Database error: '.$e->getMessage(), 0, $e);
        }
    }
}
