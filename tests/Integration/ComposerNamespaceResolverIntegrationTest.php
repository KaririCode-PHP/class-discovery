<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Scanner\ComposerNamespaceResolver;
use KaririCode\ClassDiscovery\Tests\TemporaryDirectories;
use PHPUnit\Framework\TestCase;

final class ComposerNamespaceResolverIntegrationTest extends TestCase
{
    use TemporaryDirectories;

    private string $projectRoot;

    protected function setUp(): void
    {
        $root = realpath(__DIR__ . '/../..');
        self::assertIsString($root);
        $this->projectRoot = $root;
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
        self::assertStringNotContainsString('\\\\\\', $fqcn);
    }

    public function testAutoDetectsComposerJsonFromTheWorkingDirectory(): void
    {
        $cwd = (string) getcwd();
        chdir($this->projectRoot . '/tests/Fixture/Nested');

        try {
            $resolver = new ComposerNamespaceResolver();
        } finally {
            chdir($cwd);
        }

        self::assertSame(
            'KaririCode\\ClassDiscovery\\Scanner\\FileScanner',
            $resolver->resolve($this->projectRoot . '/src/Scanner/FileScanner.php'),
        );
    }

    public function testDevAutoloadIsExcludedByDefault(): void
    {
        $resolver = new ComposerNamespaceResolver($this->projectRoot . '/composer.json');

        self::assertNull($resolver->resolve($this->projectRoot . '/tests/MetadataFactory.php'));
    }

    public function testExplicitPathWinsOverTheWorkingDirectory(): void
    {
        $project = $this->project(['Acme\\' => 'src/'], ['src/Widget.php']);

        self::assertSame('Acme\\Widget', new ComposerNamespaceResolver($project . '/composer.json')->resolve($project . '/src/Widget.php'));
    }

    public function testMissingComposerJsonYieldsNoMappings(): void
    {
        $resolver = new ComposerNamespaceResolver('/nonexistent/composer.json');

        self::assertNull($resolver->resolve($this->projectRoot . '/src/Scanner/FileScanner.php'));
    }

    public function testManualMappingToAnExistingDirectory(): void
    {
        $dir = $this->temporaryDirectory();
        mkdir($dir . '/Sub');
        touch($dir . '/Sub/Thing.php');

        $resolver = new ComposerNamespaceResolver('/nonexistent/composer.json')->addMapping('Custom\\', $dir);

        self::assertSame('Custom\\Sub\\Thing', $resolver->resolve($dir . '/Sub/Thing.php'));
    }

    public function testSymlinkedComposerJsonResolvesAgainstItsTarget(): void
    {
        $project = $this->project(['Acme\\' => 'src/'], ['src/Widget.php']);
        $elsewhere = $this->temporaryDirectory();
        symlink($project . '/composer.json', $elsewhere . '/composer.json');

        self::assertSame('Acme\\Widget', new ComposerNamespaceResolver($elsewhere . '/composer.json')->resolve($project . '/src/Widget.php'));
    }

    public function testEveryDirectoryOfAPrefixListIsMapped(): void
    {
        // Regression: mappings were keyed by prefix, so only the last directory of a list survived.
        $project = $this->project(['Acme\\' => ['src/', 'lib/']], ['src/Widget.php', 'lib/Extra.php']);
        $resolver = new ComposerNamespaceResolver($project . '/composer.json');

        self::assertSame('Acme\\Widget', $resolver->resolve($project . '/src/Widget.php'));
        self::assertSame('Acme\\Extra', $resolver->resolve($project . '/lib/Extra.php'));
    }

    public function testTheDeepestMappedDirectoryWins(): void
    {
        // Regression: the first matching directory won, so src/ swallowed src/Legacy/.
        $project = $this->project(['App\\' => 'src/', 'Legacy\\' => 'src/Legacy/'], ['src/Legacy/Old.php', 'src/New.php']);
        $resolver = new ComposerNamespaceResolver($project . '/composer.json');

        self::assertSame('Legacy\\Old', $resolver->resolve($project . '/src/Legacy/Old.php'));
        self::assertSame('App\\New', $resolver->resolve($project . '/src/New.php'));
    }

    public function testFallbackPrefixProducesAnUnqualifiedName(): void
    {
        // Regression: the empty PSR-4 prefix produced '\Widget'.
        $project = $this->project(['' => 'src/'], ['src/Widget.php']);

        self::assertSame('Widget', new ComposerNamespaceResolver($project . '/composer.json')->resolve($project . '/src/Widget.php'));
    }

    public function testNonPhpFilesDoNotResolve(): void
    {
        $project = $this->project(['Acme\\' => 'src/'], ['src/README.txt']);

        self::assertNull(new ComposerNamespaceResolver($project . '/composer.json')->resolve($project . '/src/README.txt'));
    }

    public function testMalformedEntriesAreIgnored(): void
    {
        $project = $this->project(['Acme\\' => 'src/', 'Bad\\' => 42, 'Gone\\' => 'missing/'], ['src/Widget.php']);

        self::assertSame('Acme\\Widget', new ComposerNamespaceResolver($project . '/composer.json')->resolve($project . '/src/Widget.php'));
    }

    public function testInvalidJsonYieldsNoMappings(): void
    {
        $dir = $this->temporaryDirectory();
        file_put_contents($dir . '/composer.json', '{ "autoload": ');
        mkdir($dir . '/src');
        touch($dir . '/src/Widget.php');

        self::assertNull(new ComposerNamespaceResolver($dir . '/composer.json')->resolve($dir . '/src/Widget.php'));
    }

    public function testComposerJsonWithoutAutoloadYieldsNoMappings(): void
    {
        $dir = $this->temporaryDirectory();
        file_put_contents($dir . '/composer.json', '{"name": "acme/empty"}');
        mkdir($dir . '/src');
        touch($dir . '/src/Widget.php');

        self::assertNull(new ComposerNamespaceResolver($dir . '/composer.json')->resolve($dir . '/src/Widget.php'));
    }

    public function testUnreadableComposerJsonYieldsNoMappingsWithoutAWarning(): void
    {
        $project = $this->project(['Acme\\' => 'src/'], ['src/Widget.php']);
        chmod($project . '/composer.json', 0o000);

        if (is_readable($project . '/composer.json')) {
            chmod($project . '/composer.json', 0o600);
            self::markTestSkipped('Running as root: file permissions cannot make composer.json unreadable (exercised in CI).');
        }

        try {
            self::assertNull(new ComposerNamespaceResolver($project . '/composer.json')->resolve($project . '/src/Widget.php'));
        } finally {
            chmod($project . '/composer.json', 0o600);
        }
    }

    public function testAutoDetectionStopsAtTheFilesystemRootWhenNothingIsFound(): void
    {
        $deep = $this->temporaryDirectory() . '/a/b/c';
        mkdir($deep, 0o700, true);
        $cwd = (string) getcwd();
        chdir($deep);

        try {
            $resolver = new ComposerNamespaceResolver();
        } finally {
            chdir($cwd);
        }

        // Walking up from the temp directory never reaches this project's composer.json.
        self::assertNull($resolver->resolve($this->projectRoot . '/src/Scanner/FileScanner.php'));
    }

    /**
     * A throwaway Composer project with the given PSR-4 map and empty files.
     *
     * @param array<string, mixed> $psr4
     * @param list<string>         $files
     */
    private function project(array $psr4, array $files): string
    {
        $root = $this->temporaryDirectory();
        file_put_contents($root . '/composer.json', json_encode(['autoload' => ['psr-4' => $psr4]], JSON_THROW_ON_ERROR));

        foreach ($files as $file) {
            @mkdir(dirname($root . '/' . $file), 0o700, true);
            touch($root . '/' . $file);
        }

        return $root;
    }
}
