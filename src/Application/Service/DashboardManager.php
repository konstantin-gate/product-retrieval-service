<?php

declare(strict_types=1);

namespace App\Application\Service;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Manages runtime configuration toggles and persists changes to .env.local.
 */
final readonly class DashboardManager
{
    public function __construct(
        private string $projectDir,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * Updates or adds a configuration toggle in .env.local.
     *
     * Reads existing file via Symfony Filesystem (no raw PHP file functions).
     * Skips empty lines and comments (lines starting with #).
     *
     * @param string $key   Environment variable name
     * @param string $value New value for the variable
     */
    public function setToggle(string $key, string $value): void
    {
        $envLocalPath = $this->projectDir.'/.env.local';
        $lines = [];

        if ($this->filesystem->exists($envLocalPath)) {
            $content = $this->filesystem->readFile($envLocalPath);
            $lines = \explode("\n", $content);
        }

        $keyFound = false;
        foreach ($lines as $index => $line) {
            $trimmed = \trim($line);
            if ('' === $trimmed || \str_starts_with($trimmed, '#')) {
                continue;
            }
            if (\str_starts_with($trimmed, $key.'=')) {
                $lines[$index] = $key.'='.$value;
                $keyFound = true;

                break;
            }
        }

        if (!$keyFound) {
            $lines[] = $key.'='.$value;
        }

        $filtered = \array_filter($lines, static fn (string $line): bool => '' !== \trim($line));
        $this->filesystem->dumpFile($envLocalPath, \implode("\n", $filtered)."\n");
    }

    public function getHealthStatus(): array
    {
        return [];
    }
}
