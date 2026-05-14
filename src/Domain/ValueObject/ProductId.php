<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidProductIdException;
use Symfony\Component\Uid\Uuid;

/**
 * Immutable value object wrapping a valid RFC 4122 UUID.
 *
 * Uses symfony/uid for strict UUID validation. Invalid UUIDs
 * are rejected at construction time.
 */
final readonly class ProductId
{
    /**
     * Private constructor to enforce factory method usage.
     */
    private function __construct(private Uuid $uuid)
    {
    }

    /**
     * Creates a ProductId from a UUID string.
     *
     * @param string $value UUID in RFC 4122 format
     *
     * @throws InvalidProductIdException if the UUID is invalid
     */
    public static function fromString(string $value): self
    {
        try {
            return new self(Uuid::fromString($value));
        } catch (\Exception $e) {
            throw new InvalidProductIdException('Invalid UUID: '.$value, 0, $e);
        }
    }

    public function value(): string
    {
        return $this->uuid->toRfc4122();
    }

    public function __toString(): string
    {
        return $this->value();
    }
}
