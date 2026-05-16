<?php

declare(strict_types=1);

namespace App\Domain\Contract;

/**
 * Contract for retrieving a limited list of sample product IDs.
 */
interface SampleIdProviderInterface
{
    /**
     * @param int<1, max> $limit
     *
     * @return list<string>
     */
    public function findSampleIds(int $limit): array;
}
