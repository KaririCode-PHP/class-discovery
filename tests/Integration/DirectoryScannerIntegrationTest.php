<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Cache\MemoryCacheStrategy;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Filter\StructuralFilter;
use KaririCode\ClassDiscovery\Scanner\DirectoryScanner;
use KaririCode\ClassDiscovery\Tests\FixturePaths;
use KaririCode\ClassDiscovery\Tests\TemporaryDirectories;
use PHPUnit\Framework\TestCase;

final class DirectoryScannerIntegrationTest extends TestCase
{
    use FixturePaths;
    use TemporaryDirectories;

    private DirectoryScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new DirectoryScanner();
    }

    public function testDefaultsDiscoverEveryFixture(): void
    {
        self::assertCount(12, $this->scanner->scan([self::fixturePath()]));
    }

    public function testNestedLevelIsWithinDepthOne(): void
    {
        $result = $this->scanner->setMaxDepth(1)->scan([self::fixturePath()]);

        self::assertTrue($result->hasClass(self::fixtureClass('Nested\\DeepClass')));
    }

    public function testMaxDepthExceededThrows(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root . '/a/b/c', 0o700, true);
        file_put_contents($root . '/a/b/c/Deep.php', "<?php\nnamespace T;\nclass Deep {}\n");

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8710);

        $this->scanner->setMaxDepth(1)->scan([$root]);
    }

    public function testPatternSelectsFilesByBasename(): void
    {
        $result = $this->scanner->setPattern('*Interface.php')->scan([self::fixturePath()]);

        self::assertSame([self::fixtureClass('SampleInterface')], array_keys($result->getClasses()));
    }

    public function testSymlinksAreSkippedByDefault(): void
    {
        $root = $this->temporaryDirectory();
        file_put_contents($root . '/Real.php', "<?php\nnamespace T;\nclass Real {}\n");
        $outside = $this->temporaryDirectory();
        file_put_contents($outside . '/Outside.php', "<?php\nnamespace T;\nclass Outside {}\n");
        symlink($outside, $root . '/linked');

        self::assertSame(['T\\Real'], array_keys($this->scanner->scan([$root])->getClasses()));
    }

    public function testFollowedSymlinkInsideTheRootIsTraversed(): void
    {
        $root = $this->temporaryDirectory();
        mkdir($root . '/real');
        file_put_contents($root . '/real/Inner.php', "<?php\nnamespace T;\nclass Inner {}\n");
        symlink($root . '/real', $root . '/alias');

        self::assertTrue($this->scanner->setFollowSymlinks(true)->scan([$root])->hasClass('T\\Inner'));
    }

    public function testFollowedSymlinkToASiblingSharingThePrefixIsRejected(): void
    {
        // Regression (CWE-22): '/x/src-evil' used to pass a plain prefix check against root '/x/src'.
        $base = $this->temporaryDirectory();
        mkdir($base . '/src');
        mkdir($base . '/src-evil');
        file_put_contents($base . '/src-evil/Evil.php', "<?php\nnamespace T;\nclass Evil {}\n");
        symlink($base . '/src-evil', $base . '/src/escape');

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8702);

        $this->scanner->setFollowSymlinks(true)->scan([$base . '/src']);
    }

    public function testRepeatedScansReuseTheCache(): void
    {
        // Regression (SRC-003): filters were re-added on every scan, drifting the cache key.
        $this->scanner->addFilter(new StructuralFilter(isFinal: true))->setCacheStrategy(new MemoryCacheStrategy());

        self::assertSame($this->scanner->scan([self::fixturePath()]), $this->scanner->scan([self::fixturePath()]));
    }

    public function testMissingPathThrows(): void
    {
        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8701);

        $this->scanner->scan(['/absolutely/nonexistent/path']);
    }

    public function testSingleFileMatchingThePattern(): void
    {
        self::assertTrue($this->scanner->scan([self::fixturePath('SampleEnum.php')])->hasClass(self::fixtureClass('SampleEnum')));
    }

    public function testDirectoryWithoutMatchesYieldsAnEmptyResult(): void
    {
        self::assertCount(0, $this->scanner->setPattern('*.inc')->scan([self::fixturePath()]));
    }

    public function testMaxDepthIsClampedToOne(): void
    {
        $result = $this->scanner->setMaxDepth(0)->scan([self::fixturePath()]);

        self::assertTrue($result->hasClass(self::fixtureClass('Nested\\DeepClass')));
    }

    public function testFollowedLinkToTheRootItselfIsInsideAndBoundedByDepth(): void
    {
        $root = $this->temporaryDirectory();
        file_put_contents($root . '/Real.php', "<?php\nnamespace T;\nclass Real {}\n");
        symlink($root, $root . '/self');

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8710); // a cycle, not an escape (1003)

        $this->scanner->setFollowSymlinks(true)->setMaxDepth(3)->scan([$root]);
    }

    public function testClearCacheWithoutACacheIsANoOp(): void
    {
        $this->scanner->clearCache();

        self::assertCount(12, $this->scanner->scan([self::fixturePath()]));
    }

    public function testClearCacheEmptiesTheConfiguredCache(): void
    {
        $this->scanner->setCacheStrategy(new MemoryCacheStrategy());
        $first = $this->scanner->scan([self::fixturePath()]);
        self::assertSame($first, $this->scanner->scan([self::fixturePath()]), 'served from the cache');

        $this->scanner->clearCache();

        self::assertNotSame($first, $this->scanner->scan([self::fixturePath()]));
    }
}
