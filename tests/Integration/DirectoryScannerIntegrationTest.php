<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Contract\NamespaceResolver;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Scanner\DirectoryScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectoryScanner::class)]
final class DirectoryScannerIntegrationTest extends TestCase
{
    private string $fixtureDir;
    private NamespaceResolver $resolver;

    protected function setUp(): void
    {
        $this->fixtureDir = realpath(__DIR__ . '/../Fixture');

        $this->resolver = new class ($this->fixtureDir) implements NamespaceResolver {
            public function __construct(private readonly string $baseDir)
            {
            }

            public function resolve(string $filePath): ?string
            {
                $realPath = realpath($filePath);
                if ($realPath === false || ! str_starts_with($realPath, $this->baseDir)) {
                    return null;
                }

                $relative = substr($realPath, \strlen($this->baseDir) + 1);
                $relative = str_replace('.php', '', $relative);
                $className = str_replace('/', '\\', $relative);

                return 'KaririCode\\ClassDiscovery\\Tests\\Fixture\\' . $className;
            }

            public function addMapping(string $prefix, string $directory): self
            {
                return $this;
            }
        };
    }

    public function testScanWithDefaultSettings(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir]);

        // Should find all fixture classes including nested
        self::assertGreaterThanOrEqual(8, $result->count());
    }

    public function testMaxDepthConstraint(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $scanner->setMaxDepth(1);

        // Depth 1 should still reach Nested/ (depth 0 = Fixture, depth 1 = Nested)
        $result = $scanner->scan([$this->fixtureDir]);
        self::assertGreaterThan(0, $result->count());
    }

    public function testPatternConstraint(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $scanner->setPattern('*Interface.php');

        $result = $scanner->scan([$this->fixtureDir]);

        // Only SampleInterface.php should match
        self::assertGreaterThanOrEqual(1, $result->count());
        foreach ($result as $meta) {
            self::assertStringContainsString('Interface', $meta->shortName);
        }
    }

    public function testSymlinksNotFollowedByDefault(): void
    {
        // Create a symlink inside fixture dir
        $linkPath = $this->fixtureDir . '/link_test';
        $targetPath = sys_get_temp_dir();

        if (file_exists($linkPath)) {
            unlink($linkPath);
        }

        // Only test if we can create symlinks
        if (@symlink($targetPath, $linkPath)) {
            try {
                $scanner = new DirectoryScanner($this->resolver);
                $scanner->setFollowSymlinks(false);

                // Should not crash — symlink is just skipped
                $result = $scanner->scan([$this->fixtureDir]);
                self::assertGreaterThan(0, $result->count());
            } finally {
                unlink($linkPath);
            }
        } else {
            self::markTestSkipped('Cannot create symlinks');
        }
    }

    public function testThrowsForNonexistentPath(): void
    {
        $scanner = new DirectoryScanner($this->resolver);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(1001);

        $scanner->scan(['/absolutely/nonexistent/path']);
    }

    public function testFluentInterface(): void
    {
        $scanner = new DirectoryScanner($this->resolver);

        $result = $scanner
            ->setMaxDepth(5)
            ->setPattern('*.php')
            ->setFollowSymlinks(false)
            ->scan([$this->fixtureDir]);

        self::assertGreaterThan(0, $result->count());
    }

    public function testScanSingleFile(): void
    {
        $scanner = new DirectoryScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir . '/SampleEnum.php']);

        self::assertGreaterThanOrEqual(1, $result->count());
    }
}
