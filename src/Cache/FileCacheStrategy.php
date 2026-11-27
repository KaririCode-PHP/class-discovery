<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Cache;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult as DiscoveryResultContract;
use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Exception\CacheIntegrityException;
use KaririCode\ClassDiscovery\Exception\InsecureCacheDirectoryException;
use KaririCode\ClassDiscovery\Result\AttributeMetadata;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Result\MethodMetadata;
use KaririCode\ClassDiscovery\Result\PropertyMetadata;
use Psr\Clock\ClockInterface;

/**
 * File-based cache using PHP serialization.
 *
 * Production-grade: atomic writes (unique temp file → rename).
 *
 * ## Security (fail closed)
 *
 * - The cache directory must be private: not a symlink, not world-writable and,
 *   where POSIX is available, owned by the current user — otherwise construction
 *   is refused. New directories are created 0700.
 * - Cache files are unserialized with an allow-list of this package's result
 *   classes, closing the PHP object-injection vector (CWE-502). Enums are
 *   restored natively; any other object inside attribute arguments cannot be
 *   restored faithfully, so such an entry is treated as a miss and recomputed.
 * - Keys are hashed into file names, so no key can address a path outside the
 *   cache directory (CWE-22). Temp files come from tempnam() (O_EXCL, 0600).
 *
 * ```php
 * $cache = new FileCacheStrategy('/var/cache/discovery'); // 8721 if the directory is not private
 * $scanner->setCacheStrategy($cache);
 * ```
 */
final readonly class FileCacheStrategy implements CacheStrategy
{
    private const array ALLOWED_CLASSES = [
        DiscoveryResult::class,
        ClassMetadata::class,
        MethodMetadata::class,
        PropertyMetadata::class,
        AttributeMetadata::class,
        AttributeTarget::class,
    ];

    public function __construct(
        private string $cacheDirectory,
        private ClockInterface $clock = new SystemClock(),
    ) {
        if (!is_dir($this->cacheDirectory)
            && !@mkdir($this->cacheDirectory, 0o700, true)
            && !is_dir($this->cacheDirectory)
        ) {
            throw InsecureCacheDirectoryException::uncreatable($this->cacheDirectory);
        }

        $violation = $this->directoryViolation();
        if ($violation !== null) {
            throw InsecureCacheDirectoryException::because($this->cacheDirectory, $violation);
        }
    }

    public function get(string $key): ?DiscoveryResultContract
    {
        $path = $this->keyToPath($key);
        if (!is_file($path)) {
            return null;
        }

        $entry = $this->readEntry($path);
        if ($entry === null || ($entry['expires'] > 0 && $entry['expires'] < $this->clock->now()->getTimestamp())) {
            return $this->purge($path);
        }

        if (!$entry['result'] instanceof DiscoveryResultContract) {
            $this->purge($path);

            throw CacheIntegrityException::tampered($key, 'Invalid result type');
        }

        return self::isFaithful($entry['result']) ? $entry['result'] : $this->purge($path);
    }

    /**
     * Decodes a cache file; null when it is unreadable or not a well-formed entry.
     *
     * @return array{result: mixed, expires: int}|null
     */
    private function readEntry(string $path): ?array
    {
        $payload = file_get_contents($path);

        return $payload === false
            ? null
            : self::entryFrom(@unserialize($payload, ['allowed_classes' => self::ALLOWED_CLASSES]));
    }

    /**
     * @return array{result: mixed, expires: int}|null
     */
    private static function entryFrom(mixed $data): ?array
    {
        return is_array($data) ? self::entry($data['result'] ?? null, $data['expires'] ?? null) : null;
    }

    /**
     * @return array{result: mixed, expires: int}|null
     */
    private static function entry(mixed $result, mixed $expires): ?array
    {
        return $result !== null && is_int($expires) ? ['result' => $result, 'expires' => $expires] : null;
    }

    public function set(string $key, DiscoveryResultContract $result, int $ttl = 3600): void
    {
        $path = $this->keyToPath($key);
        $tempPath = tempnam($this->cacheDirectory, 'kc_');
        if ($tempPath === false) {
            return;
        }

        $data = serialize([
            'result' => $result,
            'expires' => $ttl > 0 ? $this->clock->now()->getTimestamp() + $ttl : 0,
        ]);

        // Best effort: a cache that cannot be written only costs a recomputation.
        if (file_put_contents($tempPath, $data, LOCK_EX) === false || !rename($tempPath, $path)) {
            @unlink($tempPath);
        }
    }

    public function delete(string $key): void
    {
        $path = $this->keyToPath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function clear(): void
    {
        $files = glob($this->cacheDirectory . '/discovery_*.cache');
        if ($files === false) {
            return;
        }

        array_map('unlink', $files);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    private function keyToPath(string $key): string
    {
        return $this->cacheDirectory . '/discovery_' . hash('xxh128', $key) . '.cache';
    }

    private function purge(string $path): null
    {
        @unlink($path);

        return null;
    }

    private function directoryViolation(): ?string
    {
        $permissions = fileperms($this->cacheDirectory);

        return match (true) {
            is_link($this->cacheDirectory) => 'it is a symbolic link',
            $permissions === false => 'its permissions cannot be read',
            ($permissions & 0o002) !== 0 => 'it is world-writable',
            function_exists('posix_geteuid') && fileowner($this->cacheDirectory) !== posix_geteuid()
                => 'it is owned by another user',
            default => null,
        };
    }

    /**
     * False when an attribute instance, or an object inside its arguments, is outside the allow-list
     * (restored as __PHP_Incomplete_Class): the cached copy would differ from a
     * fresh scan, so it must not be served.
     */
    private static function isFaithful(DiscoveryResultContract $result): bool
    {
        foreach ($result->getClasses() as $class) {
            foreach (self::attributesOf($class) as $attribute) {
                if ($attribute->instance instanceof \__PHP_Incomplete_Class || self::containsIncompleteObject($attribute->arguments)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return iterable<AttributeMetadata>
     */
    private static function attributesOf(ClassMetadata $class): iterable
    {
        yield from $class->attributes;

        foreach ($class->methods as $method) {
            yield from $method->attributes;
        }

        foreach ($class->properties as $property) {
            yield from $property->attributes;
        }
    }

    private static function containsIncompleteObject(mixed $value): bool
    {
        if ($value instanceof \__PHP_Incomplete_Class) {
            return true;
        }

        return is_array($value)
            && array_any($value, static fn(mixed $item): bool => self::containsIncompleteObject($item));
    }
}
