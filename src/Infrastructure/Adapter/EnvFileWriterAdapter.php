<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Contract\EnvFileWriterInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Infrastructure adapter for environment file I/O using Symfony Filesystem.
 */
final readonly class EnvFileWriterAdapter implements EnvFileWriterInterface
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    public function readFile(string $path): string
    {
        if (!$this->filesystem->exists($path)) {
            return '';
        }

        return $this->filesystem->readFile($path);
    }

    public function writeFile(string $path, string $content): void
    {
        $this->filesystem->dumpFile($path, $content);
    }
}
