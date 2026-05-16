<?php

declare(strict_types=1);

namespace App\Domain\Contract;

/**
 * Contract for reading and writing environment configuration files.
 * Implemented in Infrastructure using Symfony Filesystem or similar.
 */
interface EnvFileWriterInterface
{
    /**
     * Reads the content of a file if it exists, otherwise returns empty string.
     */
    public function readFile(string $path): string;

    /**
     * Atomically writes content to a file.
     */
    public function writeFile(string $path, string $content): void;
}
