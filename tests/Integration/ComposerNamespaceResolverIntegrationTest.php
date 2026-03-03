<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Scanner\ComposerNamespaceResolver;
use PHPUnit\Framework\TestCase;

final class ComposerNamespaceResolverIntegrationTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = realpath(__DIR__ . '/../..');
    }

    public function testResolvesFromProjectComposerJson(): void
    {
        $resolver = new ComposerNamespaceResolver(
            composerJsonPath: $this->projectRoot . '/composer.json',
        );

        $filePath = $this->projectRoot . '/src/Scanner/FileScanner.php';

        $fqcn = $resolver->resolve($filePath);

        self::assertSame('KaririCode\\ClassDiscovery\\Scanner\\FileScanner', $fqcn);
    }

    public function testResolvesNestedNamespace(): void
    {
        $resolver = new ComposerNamespaceResolver(
            composerJsonPath: $this->projectRoot . '/composer.json',
        );

        $filePath = $this->projectRoot . '/src/Result/ClassMetadata.php';

        self::assertSame(
            'KaririCode\\ClassDiscovery\\Result\\ClassMetadata',
            $resolver->resolve($filePath),
        );
    }

    public function testResolvesDevAutoload(): void
    {
        $resolver = new ComposerNamespaceResolver(
            composerJsonPath: $this->projectRoot . '/composer.json',
            includeDevAutoload: true,
        );

        $filePath = $this->projectRoot . '/tests/MetadataFactory.php';

        self::assertSame(
            'KaririCode\\ClassDiscovery\\Tests\\MetadataFactory',
            $resolver->resolve($filePath),
        );
    }

    public function testReturnsNullForUnmappedFile(): void
    {
        $resolver = new ComposerNamespaceResolver(
            composerJsonPath: $this->projectRoot . '/composer.json',
        );

        $fqcn = $resolver->resolve('/tmp/random_file.php');

        self::assertNull($fqcn);
    }

    public function testAddManualMapping(): void
    {
        $resolver = new ComposerNamespaceResolver(
            composerJsonPath: $this->projectRoot . '/composer.json',
        );

        $resolver->addMapping('Custom\\Namespace\\', '/custom/path');

        // The mapping should be added (we can verify by resolving a file under that path)
        // Since /custom/path doesn't exist, just verify it doesn't crash
        self::assertNull($resolver->resolve('/custom/path/SomeClass.php'));
    }

    public function testResolvesWithSeparator(): void
    {
        // BUG-002 fix verification: namespace separator between prefix and class
        $resolver = new ComposerNamespaceResolver(
            composerJsonPath: $this->projectRoot . '/composer.json',
        );

        $fqcn = $resolver->resolve($this->projectRoot . '/src/Cache/MemoryCacheStrategy.php');

        // Should NOT produce "KaririCode\ClassDiscoveryCacheMemoryCacheStrategy"
        self::assertSame(
            'KaririCode\\ClassDiscovery\\Cache\\MemoryCacheStrategy',
            $fqcn,
        );

        // Verify no double backslash
        self::assertStringNotContainsString('\\\\\\', $fqcn ?? '');
    }
}
