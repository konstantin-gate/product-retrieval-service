<?php

declare(strict_types=1);

namespace App\Infrastructure\Normalizer;

use App\Domain\ValueObject\Price;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer for Price value objects.
 *
 * Returns integer amount for JSON format, formatted string otherwise.
 */
final readonly class PriceNormalizer implements NormalizerInterface
{
    /**
     * Normalizes a Price object.
     *
     * @param mixed                $object  Price value object
     * @param string|null          $format  Target format
     * @param array<string, mixed> $context Serialization context
     *
     * @return string|int Formatted string or integer amount
     *
     * @throws \InvalidArgumentException if the object is not a Price instance
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): string|int
    {
        if (!$object instanceof Price) {
            throw new \InvalidArgumentException('Object must be an instance of Price');
        }

        if ('json' === $format) {
            return (int) $object->amount();
        }

        return $object->formatted();
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
        return $data instanceof Price;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Price::class => false,
        ];
    }
}
