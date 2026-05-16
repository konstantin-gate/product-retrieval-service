<?php

declare(strict_types=1);

namespace App\Infrastructure\Seeder;

use App\Domain\Contract\SeederInterface;
use App\Infrastructure\Service\DataSeederService;

/**
 * Adapter bridging SeederInterface to ProductSeeder and DataSeederService.
 */
final readonly class SeederAdapter implements SeederInterface
{
    public function __construct(
        private ProductSeeder $productSeeder,
        private DataSeederService $dataSeederService,
    ) {
    }

    public function seed(int $count): void
    {
        $products = $this->productSeeder->generate($count);
        $this->dataSeederService->seed($products);
    }
}
