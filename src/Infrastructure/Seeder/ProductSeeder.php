<?php

declare(strict_types=1);

namespace App\Infrastructure\Seeder;

use App\Domain\DTO\ProductDTO;
use App\Domain\ValueObject\Price;
use App\Domain\ValueObject\ProductId;
use Faker\Factory;
use Symfony\Component\Uid\Uuid;

/**
 * Generates fake ProductDTO objects for seeding purposes.
 */
final readonly class ProductSeeder
{
    private const MIN_PRICE_MINOR = 10000;
    private const MAX_PRICE_MINOR = 1000000;

    /**
     * @param int<1, max> $count Number of products to generate
     *
     * @return list<ProductDTO>
     */
    public function generate(int $count): array
    {
        $faker = Factory::create();
        $products = [];

        for ($i = 0; $i < $count; ++$i) {
            $products[] = new ProductDTO(
                ProductId::fromString(Uuid::v4()->toRfc4122()),
                $faker->words(3, true),
                Price::of((string) $faker->numberBetween(self::MIN_PRICE_MINOR, self::MAX_PRICE_MINOR)),
                $faker->paragraph(),
            );
        }

        return $products;
    }
}
