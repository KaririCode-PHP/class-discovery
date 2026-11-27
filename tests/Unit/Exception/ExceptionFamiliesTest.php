<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Exception;

use KaririCode\ClassDiscovery\Exception\CacheIntegrityException;
use KaririCode\ClassDiscovery\Exception\CircularDependencyException;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Exception\InsecureCacheDirectoryException;
use KaririCode\ClassDiscovery\Exception\InvalidConfigurationException;
use KaririCode\ClassDiscovery\Exception\PathNotFoundException;
use KaririCode\ClassDiscovery\Exception\ScanLimitExceededException;
use KaririCode\ClassDiscovery\Exception\SymlinkEscapeException;
use KaririCode\Contract\Exception\Configuration;
use KaririCode\Contract\Exception\ExceptionInterface;
use KaririCode\Contract\Exception\NotFound;
use KaririCode\Contract\Exception\Runtime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExceptionFamiliesTest extends TestCase
{
    /**
     * @param class-string $family
     */
    #[DataProvider('factories')]
    public function testFactoryCarriesItsCodeMessageAndContractFamily(DiscoveryException $exception, int $code, string $family, string $fragment): void
    {
        self::assertSame($code, $exception->getCode());
        self::assertInstanceOf($family, $exception);
        self::assertStringContainsString($fragment, $exception->getMessage());
    }

    /**
     * @return \Generator<string, array{DiscoveryException, int, class-string, string}>
     */
    public static function factories(): \Generator
    {
        yield 'path not found' => [PathNotFoundException::at('/missing'), 8701, NotFound::class, '/missing'];
        yield 'symlink escape' => [SymlinkEscapeException::outside('/r/link', '/etc', '/r'), 8702, Runtime::class, "'/etc' outside scan root '/r'"];
        yield 'depth limit' => [ScanLimitExceededException::depth(15, 10), 8710, Runtime::class, 'depth 15 exceeds limit 10'];
        yield 'file limit' => [ScanLimitExceededException::files(20_000, 10_000), 8711, Runtime::class, 'count 20000 exceeds limit 10000'];
        yield 'tampered cache entry' => [CacheIntegrityException::tampered('k', 'Invalid result type'), 8720, Runtime::class, "'k' is corrupted"];
        yield 'insecure cache directory' => [InsecureCacheDirectoryException::because('/tmp/c', 'it is world-writable'), 8721, Configuration::class, 'world-writable'];
        yield 'uncreatable cache directory' => [InsecureCacheDirectoryException::uncreatable('/proc/c'), 8722, Configuration::class, '/proc/c'];
        yield 'circular dependency' => [CircularDependencyException::chain('A → B → A'), 8730, Runtime::class, 'A → B → A'];
        yield 'invalid configuration' => [InvalidConfigurationException::option('max_files', 'int', 'string'), 8740, Configuration::class, "'max_files': expected int, got string"];
    }

    public function testEveryFailureIsCatchableThroughTheContractRoot(): void
    {
        $this->expectException(ExceptionInterface::class);
        $this->expectExceptionCode(8701);

        throw PathNotFoundException::at('/x');
    }
}
