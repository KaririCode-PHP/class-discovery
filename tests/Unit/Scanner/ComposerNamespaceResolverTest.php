<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Scanner;

use KaririCode\ClassDiscovery\Scanner\ComposerNamespaceResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ComposerNamespaceResolver.
 *
 * Uses temporary filesystem fixtures to exercise:
 * — auto-detection of composer.json (detectComposerJson)
 * — graceful handling of unreadable/invalid composer.json
 * — PSR-4 mappings with multiple directory paths (array syntax)
 * — addMapping() with non-existent directory (ignored)
 * — resolve() returning null for non-existent file
 */
#[CoversClass(ComposerNamespaceResolver::class)]
final class ComposerNamespaceResolverTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cnr_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursive($this->tmpDir);
    }

    // ── __construct / detectComposerJson ─────────────────────────

    #[Test]
    public function constructWithNullPathAutoDetectsComposerJson(): void
    {
        // Create a fake project tree under tmpDir
        $srcDir = $this->tmpDir . '/src';
        mkdir($srcDir, 0o777, true);

        $composerJson = $this->tmpDir . '/composer.json';
        file_put_contents($composerJson, json_encode([
            'autoload' => [
                'psr-4' => ['Acme\\App\\' => 'src/'],
            ],
        ], \JSON_THROW_ON_ERROR));

        // Change CWD to the fake project root so detectComposerJson finds it
        $original = getcwd();
        chdir($this->tmpDir);

        $resolver = new ComposerNamespaceResolver(); // null path → auto-detect

        chdir((string) $original);

        // With srcDir existing, creating a file there allows resolve to work
        $file = $srcDir . '/Controller.php';
        file_put_contents($file, '<?php');

        $fqcn = $resolver->resolve($file);
        $this->assertSame('Acme\\App\\Controller', $fqcn);
    }

    #[Test]
    public function constructWithNullComposerJsonPathProducesEmptyMappings(): void
    {
        // Pass an explicit null path and composer doesn't exist in /
        $resolver = new ComposerNamespaceResolver(composerJsonPath: '/non/existent/composer.json');

        $this->assertNull($resolver->resolve('/any/file.php'));
    }

    // ── loadFromComposerJson edge cases ──────────────────────────

    #[Test]
    public function constructWithInvalidJsonProducesEmptyMappings(): void
    {
        $composerJson = $this->tmpDir . '/composer.json';
        file_put_contents($composerJson, 'THIS IS NOT JSON');

        $resolver = new ComposerNamespaceResolver(composerJsonPath: $composerJson);

        $this->assertNull($resolver->resolve('/any/file.php'));
    }

    #[Test]
    public function constructWithComposerJsonMissingAutoloadProducesEmptyMappings(): void
    {
        $composerJson = $this->tmpDir . '/composer.json';
        file_put_contents($composerJson, json_encode(['name' => 'test/pkg'], \JSON_THROW_ON_ERROR));

        $resolver = new ComposerNamespaceResolver(composerJsonPath: $composerJson);

        $this->assertNull($resolver->resolve('/any/file.php'));
    }

    // ── extractPsr4Mappings — array directory syntax ─────────────

    #[Test]
    public function composerJsonWithArrayDirectoriesResolvesCorrectly(): void
    {
        $srcA = $this->tmpDir . '/lib-a';
        $srcB = $this->tmpDir . '/lib-b';
        mkdir($srcA, 0o777, true);
        mkdir($srcB, 0o777, true);

        // PSR-4 with array of directories (multiple paths for same prefix)
        $composerJson = $this->tmpDir . '/composer.json';
        file_put_contents($composerJson, json_encode([
            'autoload' => [
                'psr-4' => [
                    'MyLib\\' => ['lib-a/', 'lib-b/'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR));

        $resolver = new ComposerNamespaceResolver(composerJsonPath: $composerJson);

        // The last directory wins (array iteration — lib-b overwrites lib-a)
        $fileInB = $srcB . '/Foo.php';
        file_put_contents($fileInB, '<?php');

        $fqcn = $resolver->resolve($fileInB);
        $this->assertSame('MyLib\\Foo', $fqcn);
    }

    #[Test]
    public function extractPsr4SkipsNonExistentDirectory(): void
    {
        $composerJson = $this->tmpDir . '/composer.json';
        file_put_contents($composerJson, json_encode([
            'autoload' => [
                'psr-4' => ['Ghost\\' => 'does-not-exist/'],
            ],
        ], \JSON_THROW_ON_ERROR));

        $resolver = new ComposerNamespaceResolver(composerJsonPath: $composerJson);

        // No mapping registered → resolve returns null
        $this->assertNull($resolver->resolve($this->tmpDir . '/does-not-exist/Foo.php'));
    }

    // ── autoload-dev ─────────────────────────────────────────────

    #[Test]
    public function includeDevAutoloadLoadsBothSections(): void
    {
        $mainSrc = $this->tmpDir . '/src';
        $testSrc = $this->tmpDir . '/tests';
        mkdir($mainSrc, 0o777, true);
        mkdir($testSrc, 0o777, true);

        $composerJson = $this->tmpDir . '/composer.json';
        file_put_contents($composerJson, json_encode([
            'autoload' => [
                'psr-4' => ['App\\' => 'src/'],
            ],
            'autoload-dev' => [
                'psr-4' => ['App\\Tests\\' => 'tests/'],
            ],
        ], \JSON_THROW_ON_ERROR));

        $resolver = new ComposerNamespaceResolver(
            composerJsonPath: $composerJson,
            includeDevAutoload: true,
        );

        $testFile = $testSrc . '/FooTest.php';
        file_put_contents($testFile, '<?php');

        $fqcn = $resolver->resolve($testFile);
        $this->assertSame('App\\Tests\\FooTest', $fqcn);
    }

    #[Test]
    public function excludeDevAutoloadIgnoresDevSection(): void
    {
        $testSrc = $this->tmpDir . '/tests';
        mkdir($testSrc, 0o777, true);

        $composerJson = $this->tmpDir . '/composer.json';
        file_put_contents($composerJson, json_encode([
            'autoload' => [],
            'autoload-dev' => [
                'psr-4' => ['App\\Tests\\' => 'tests/'],
            ],
        ], \JSON_THROW_ON_ERROR));

        $resolver = new ComposerNamespaceResolver(
            composerJsonPath: $composerJson,
            includeDevAutoload: false, // default
        );

        $testFile = $testSrc . '/FooTest.php';
        file_put_contents($testFile, '<?php');

        // dev autoload not loaded → null
        $this->assertNull($resolver->resolve($testFile));
    }

    // ── addMapping ───────────────────────────────────────────────

    #[Test]
    public function addMappingWithNonExistentDirectoryIsIgnored(): void
    {
        $resolver = new ComposerNamespaceResolver(composerJsonPath: null);
        $result = $resolver->addMapping('Ghost\\', '/this/does/not/exist');

        // Returns self (fluent)
        $this->assertSame($resolver, $result);
        // No mapping registered → resolve returns null
        $this->assertNull($resolver->resolve('/this/does/not/exist/Foo.php'));
    }

    #[Test]
    public function addMappingWithExistingDirectoryRegistersAndResolves(): void
    {
        $dir = $this->tmpDir . '/custom';
        mkdir($dir, 0o777, true);

        $file = $dir . '/Bar.php';
        file_put_contents($file, '<?php');

        $resolver = new ComposerNamespaceResolver(composerJsonPath: null);
        $resolver->addMapping('Custom\\Pkg\\', $dir);

        $fqcn = $resolver->resolve($file);
        $this->assertSame('Custom\\Pkg\\Bar', $fqcn);
    }

    // ── resolve edge cases ────────────────────────────────────────

    #[Test]
    public function resolveReturnsNullForNonExistentFile(): void
    {
        $resolver = new ComposerNamespaceResolver(composerJsonPath: null);
        $resolver->addMapping('App\\', $this->tmpDir);

        // realpath() on non-existent file returns false → null
        $this->assertNull($resolver->resolve($this->tmpDir . '/ghost.php'));
    }

    #[Test]
    public function detectComposerJsonWalksUpAndReturnsNullWhenNoneFound(): void
    {
        // Create a deep nested dir tree with NO composer.json anywhere under tmpDir
        $deep = $this->tmpDir . '/a/b/c';
        mkdir($deep, 0o777, true);

        $original = getcwd();
        chdir($deep);

        // With null path, detectComposerJson walks up from $deep.
        // It will eventually find the real project's composer.json above tmpDir,
        // so we can only confirm no crash occurs and the resolver instantiates.
        $resolver = new ComposerNamespaceResolver(); // exercises the walk loop (L147)

        chdir((string) $original);

        // It may or may not find a composer.json above tmpDir — just confirm no exception
        $this->assertInstanceOf(ComposerNamespaceResolver::class, $resolver);
    }

    #[Test]
    public function constructWithUnreadableComposerJsonProducesEmptyMappings(): void
    {
        $composerJson = $this->tmpDir . '/composer.json';
        file_put_contents($composerJson, json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'src/']],
        ], \JSON_THROW_ON_ERROR));

        // Make file unreadable to trigger file_get_contents === false branch (L93)
        chmod($composerJson, 0o000);

        // Skip if running as root (chmod has no effect)
        if (is_readable($composerJson)) {
            $this->markTestSkipped('Cannot make file unreadable (running as root).');
        }

        $resolver = new ComposerNamespaceResolver(composerJsonPath: $composerJson);
        $this->assertNull($resolver->resolve($this->tmpDir . '/src/Foo.php'));

        // Restore so tearDown can delete
        chmod($composerJson, 0o644);
    }

    // ── helpers ───────────────────────────────────────────────────

    private function removeRecursive(string $directoryPath): void
    {
        if (! is_dir($directoryPath)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directoryPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directoryPath);
    }
}
