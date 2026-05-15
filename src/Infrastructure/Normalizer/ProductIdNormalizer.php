<?php

declare(strict_types=1);

namespace App\Infrastructure\Normalizer;

use App\Domain\ValueObject\ProductId;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer for ProductId value objects.
 *
 * Returns the UUID string representation.
 */
final readonly class ProductIdNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * Normalizes a ProductId object.
     *
     * @param mixed                $object  ProductId value object
     * @param string|null          $format  Target format
     * @param array<string, mixed> $context Serialization context
     *
     * @return string UUID string in RFC 4122 format
     *
     * @throws \InvalidArgumentException if the object is not a ProductId instance
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        if (!$object instanceof ProductId) {
            throw new \InvalidArgumentException('Object must be an instance of ProductId');
        }

        return $object->value();
    }

    /**
     * Checks whether this normalizer supports the given data.
     *
     * @param mixed                $data    Data to check
     * @param string|null          $format  Target format
     * @param array<string, mixed> $context Serialization context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ProductId;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return ProductId::fromString((string) $data);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return ProductId::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ProductId::class => true,
        ];
    }
}
