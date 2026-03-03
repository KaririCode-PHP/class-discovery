<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Exception;

use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use PHPUnit\Framework\TestCase;

final class DiscoveryExceptionTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('namedConstructorProvider')]
    public function testNamedConstructorCodeAndMessage(string $method, array $args, int $expectedCode, string $expectedSubstring): void
    {
        $exception = DiscoveryException::$method(...$args);

        self::assertInstanceOf(DiscoveryException::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame($expectedCode, $exception->getCode());
        self::assertStringContainsString($expectedSubstring, $exception->getMessage());
    }

    public static function namedConstructorProvider(): \Generator
    {
        yield 'pathNotFound' => ['pathNotFound', ['/missing'], 1001, '/missing'];
        yield 'pathTraversalDetected' => ['pathTraversalDetected', ['/evil/../etc', '/etc'], 1002, 'traversal'];
        yield 'symlinkEscape' => ['symlinkEscape', ['/a/link', '/outside', '/root'], 1003, 'Symlink'];
        yield 'maxDepthExceeded' => ['maxDepthExceeded', [15, 10], 1004, '15'];
        yield 'maxFilesExceeded' => ['maxFilesExceeded', [20000, 10000], 1005, '20000'];
        yield 'scanTimeout' => ['scanTimeout', [5.5, 3.0], 1006, '5.50'];
        yield 'tokenParseFailure' => ['tokenParseFailure', ['/bad.php', 'syntax error'], 1007, 'syntax error'];
        yield 'namespaceResolutionError' => ['namespaceResolutionError', ['/f.php', 'no psr4'], 1008, 'no psr4'];
        yield 'cacheCorruption' => ['cacheCorruption', ['key-1', 'unserialize failed'], 1009, 'corrupted'];
        yield 'circularDependency' => ['circularDependency', ['A → B → A'], 1010, 'A → B → A'];
    }

    public function testUniqueErrorCodes(): void
    {
        $codes = [1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009, 1010];

        self::assertSame($codes, array_unique($codes));
        self::assertCount(10, $codes);
    }

    public function testIsFinal(): void
    {
        $ref = new \ReflectionClass(DiscoveryException::class);

        self::assertTrue($ref->isFinal());
    }
}
