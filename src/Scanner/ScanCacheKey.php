<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Contract\ClassFilter;

/**
 * Deterministic cache key for one scan request.
 *
 * Two requests share a key only when they name the same scanner, the same paths
 * (after realpath) and filters with the same *configuration*: each filter's
 * serialized state participates, not just its class name, so
 * `AttributeFilter('A')` and `AttributeFilter('B')` can never collide in a
 * shared cache.
 *
 * @internal Shared by the scanners of this package; not part of the public API.
 */
final readonly class ScanCacheKey
{
    private function __construct(
        public string $value,
    ) {}

    /**
     * @param array<string>      $paths
     * @param list<ClassFilter>  $filters
     */
    public static function of(string $scanner, array $paths, array $filters): self
    {
        $parts = [$scanner];

        foreach ($paths as $path) {
            $realPath = realpath($path);
            $parts[] = $realPath === false ? $path : $realPath;
        }

        foreach ($filters as $filter) {
            $parts[] = self::fingerprint($filter);
        }

        return new self(hash('xxh128', implode("\0", $parts)));
    }

    /**
     * Configuration-sensitive identity of a filter. A filter that cannot be
     * serialized (e.g. one holding a closure) falls back to object identity:
     * it may miss across processes but can never produce a false hit.
     */
    private static function fingerprint(ClassFilter $filter): string
    {
        try {
            return serialize($filter);
        } catch (\Exception) {
            return $filter::class . '#' . spl_object_id($filter);
        }
    }
}
