<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests;

use PHPUnit\Framework\Attributes\After;

/**
 * Isolated temporary directories, removed after each test.
 *
 * Removal never follows symlinks, so a test that links outside its sandbox
 * cannot make the cleanup delete anything beyond it.
 */
trait TemporaryDirectories
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/kc_classdiscovery_' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700, true);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    #[After]
    protected function removeTemporaryDirectories(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            self::removeTree($directory);
        }

        $this->temporaryDirectories = [];
    }

    private static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        chmod($path, 0o700);
        $entries = scandir($path);
        foreach (array_diff($entries === false ? [] : $entries, ['.', '..']) as $entry) {
            self::removeTree($path . '/' . $entry);
        }

        rmdir($path);
    }
}
