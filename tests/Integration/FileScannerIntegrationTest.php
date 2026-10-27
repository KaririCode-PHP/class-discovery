<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Cache\MemoryCacheStrategy;
use KaririCode\ClassDiscovery\Exception\DiscoveryException;
use KaririCode\ClassDiscovery\Filter\StructuralFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Scanner\ComposerNamespaceResolver;
use KaririCode\ClassDiscovery\Scanner\FileScanner;
use KaririCode\ClassDiscovery\Tests\FixturePaths;
use KaririCode\ClassDiscovery\Tests\TemporaryDirectories;
use PHPUnit\Framework\TestCase;

/**
 * FileScanner reads the namespace from each file's own declaration; the
 * ComposerNamespaceResolver is passed for API parity only (SRC-001).
 */
final class FileScannerIntegrationTest extends TestCase
{
    use FixturePaths;
    use TemporaryDirectories;

    private FileScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new FileScanner();
    }

    public function testDiscoversEveryFixtureAndSkipsFilesWithoutTypes(): void
    {
        $result = $this->scanner->scan([self::fixturePath()]);

        self::assertCount(12, $result); // 13 fixture files; helper_functions.php declares no type
        self::assertFalse($result->hasErrors());
        self::assertGreaterThan(0.0, $result->getScanDuration());
        self::assertCount(0, $this->scanner->scan([self::fixturePath('helper_functions.php')]));
    }

    public function testDetectsEachKindOfDeclaration(): void
    {
        self::assertTrue($this->fixture('SampleInterface')->isInterface);
        self::assertTrue($this->fixture('SampleEnum')->isEnum);
        self::assertTrue($this->fixture('SampleTrait')->isTrait);
        self::assertTrue($this->fixture('SampleAbstractClass')->isAbstract);

        $finalReadonly = $this->fixture('SampleFinalReadonlyClass');
        self::assertTrue($finalReadonly->isFinal);
        self::assertTrue($finalReadonly->isReadonly);
    }

    public function testImplementsIsKeptWhenExtendsPrecedesIt(): void
    {
        $meta = $this->fixture('SampleClass');

        self::assertSame(self::fixtureClass('SampleAbstractClass'), $meta->parent);
        self::assertSame([self::fixtureClass('SampleInterface')], $meta->interfaces);
    }

    public function testMemberModifiersDoNotLeakIntoClassMetadata(): void
    {
        // ChildClass promotes `private readonly float $extra`; the class itself is not readonly.
        $meta = $this->fixture('ChildClass');

        self::assertTrue($meta->isFinal);
        self::assertFalse($meta->isReadonly);
        self::assertSame(self::fixtureClass('SampleClass'), $meta->parent);
    }

    public function testMemberAttributesDoNotLeakIntoClassAttributes(): void
    {
        $meta = $this->scanSource('Leak', <<<'PHP'
            namespace T;
            #[ClassLevel]
            final class Leak extends Base implements Contract
            {
                #[PropertyLevel] public int $count = 0;
                #[MethodLevel] public function run(): void {}
            }
            PHP);

        self::assertSame(['T\\ClassLevel'], array_keys($meta->attributes));
        self::assertSame(['T\\Contract'], $meta->interfaces);
    }

    public function testGroupedAttributesAreAllCaptured(): void
    {
        $meta = $this->scanSource('Grouped', <<<'PHP'
            namespace T;
            #[First, Second(methods: ['GET', 'POST'], options: ['a' => [1, 2]]), \Fq\Third]
            final class Grouped {}
            PHP);

        self::assertSame(['T\\First', 'T\\Second', 'Fq\\Third'], array_keys($meta->attributes));
    }

    public function testRepeatedAttributeIsFlagged(): void
    {
        $meta = $this->scanSource('Tagged', "namespace T;\n#[Tag('a')]\n#[Tag('b')]\n#[Once]\nclass Tagged {}");

        self::assertTrue($meta->attributes['T\\Tag']->isRepeated);
        self::assertFalse($meta->attributes['T\\Once']->isRepeated);
    }

    public function testClassConstantFetchBeforeTheDeclarationIsNotADeclaration(): void
    {
        $meta = $this->scanSource('Real', "namespace T;\nconst ALIAS = Other::class;\nfinal class Real {}");

        self::assertSame('T\\Real', $meta->name);
        self::assertTrue($meta->isFinal);
    }

    public function testAnonymousClassesAreNotDeclarations(): void
    {
        $meta = $this->scanSource('Named', "namespace T;\n\$a = new class {};\n\$b = new readonly class {};\nclass Named {}");

        self::assertSame('T\\Named', $meta->name);
        self::assertFalse($meta->isReadonly);
    }

    public function testAttributesOfEarlierCodeDoNotAttachToTheClass(): void
    {
        $meta = $this->scanSource('After', "namespace T;\n#[Pure]\nfunction helper(): int { return 1; }\nfinal class After {}");

        self::assertSame([], $meta->attributes);
        self::assertTrue($meta->isFinal);
    }

    public function testInterfaceExtendsListsParentInterfaces(): void
    {
        $meta = $this->scanSource('Child', "namespace T;\ninterface Child extends First, \\Second {}");

        self::assertNull($meta->parent);
        self::assertSame(['T\\First', 'Second'], $meta->interfaces);
    }

    public function testEnumBackingTypeIsNotMistakenForAnInterface(): void
    {
        $meta = $this->scanSource('Status', "namespace T;\nenum Status: string implements HasLabel { case On = 'on'; }");

        self::assertTrue($meta->isEnum);
        self::assertSame(['T\\HasLabel'], $meta->interfaces);
    }

    public function testFiltersNarrowTheResult(): void
    {
        $result = $this->scanner->addFilter(new StructuralFilter(isFinal: true))->scan([self::fixturePath()]);

        self::assertGreaterThan(0, $result->count());
        foreach ($result as $meta) {
            self::assertTrue($meta->isFinal, "{$meta->name} should be final");
        }
    }

    public function testCacheHitReturnsTheSameInstance(): void
    {
        $this->scanner->setCacheStrategy(new MemoryCacheStrategy());

        self::assertSame($this->scanner->scan([self::fixturePath()]), $this->scanner->scan([self::fixturePath()]));
    }

    public function testClearCacheForcesAFreshScan(): void
    {
        $this->scanner->setCacheStrategy(new MemoryCacheStrategy());
        $first = $this->scanner->scan([self::fixturePath()]);
        $this->scanner->clearCache();

        self::assertNotSame($first, $this->scanner->scan([self::fixturePath()]));
    }

    public function testNestedDirectoriesAreScanned(): void
    {
        $meta = $this->scanner->scan([self::fixturePath('Nested')])->getClass(self::fixtureClass('Nested\\DeepClass'));

        self::assertNotNull($meta);
        self::assertSame('DeepClass', $meta->shortName);
    }

    public function testMissingPathThrows(): void
    {
        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8701);

        $this->scanner->scan(['/nonexistent/path']);
    }

    public function testMaxFilesIsEnforced(): void
    {
        $scanner = new FileScanner(maxFiles: 2);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(8711);

        $scanner->scan([self::fixturePath()]);
    }

    public function testFilesAboveTheSizeLimitAreSkipped(): void
    {
        $scanner = new FileScanner(maxFileSize: 10);

        self::assertCount(0, $scanner->scan([self::fixturePath('SampleEnum.php')]));
    }

    public function testMaxFilesBoundaryIsInclusive(): void
    {
        // GLOB_BRACE is not portable (absent on musl), so count both levels explicitly.
        $files = count(glob(self::fixturePath() . '/*.php') ?: []) + count(glob(self::fixturePath() . '/*/*.php') ?: []);

        self::assertCount(12, new FileScanner(maxFiles: $files)->scan([self::fixturePath()]));

        $this->expectExceptionCode(8711);
        new FileScanner(maxFiles: $files - 1)->scan([self::fixturePath()]);
    }

    public function testMaxFileSizeBoundaryIsInclusive(): void
    {
        $file = self::fixturePath('SampleEnum.php');
        $size = (int) filesize($file);

        self::assertCount(1, new FileScanner(maxFileSize: $size)->scan([$file]));
        self::assertCount(0, new FileScanner(maxFileSize: $size - 1)->scan([$file]));
    }

    public function testScanDurationIsMeasuredInSeconds(): void
    {
        $duration = $this->scanner->scan([self::fixturePath()])->getScanDuration();

        self::assertGreaterThan(0.0, $duration);
        self::assertLessThan(60.0, $duration);
    }

    public function testClearCacheWithoutACacheIsANoOp(): void
    {
        $this->scanner->clearCache();

        self::assertCount(12, $this->scanner->scan([self::fixturePath()]));
    }

    public function testCommentsBetweenKeywordsAndNamesAreTolerated(): void
    {
        $meta = $this->scanSource('Tight', "namespace /* ns */T;\nfinal class /* doc */Tight extends /* base */ Base {}");

        self::assertSame('T\\Tight', $meta->name);
        self::assertSame('T\\Base', $meta->parent);
    }

    public function testBracedGlobalNamespaceYieldsAnUnqualifiedName(): void
    {
        $meta = $this->scanSource('Glob', "namespace {\n    final class Glob {}\n}");

        self::assertSame('Glob', $meta->name);
        self::assertSame('', $meta->namespace);
    }

    public function testAdjacentAttributeGroupsAreBothCaptured(): void
    {
        $meta = $this->scanSource('Adjacent', "namespace T;\n#[First]#[Second]\nfinal class Adjacent {}");

        self::assertSame(['T\\First', 'T\\Second'], array_keys($meta->attributes));
    }

    public function testMalformedHeadersNeitherCrashNorWarn(): void
    {
        $unterminated = $this->scanSource('Open', "namespace T;\n#[Broken(\nfinal class Open {}");
        self::assertSame(['T\\Broken'], array_keys($unterminated->attributes));

        $truncated = $this->scanSource('Truncated', "namespace T;\nfinal class Truncated extends Base");
        self::assertSame('T\\Base', $truncated->parent);
    }

    public function testOnlyPhpFilesAreParsed(): void
    {
        $dir = $this->temporaryDirectory();
        file_put_contents($dir . '/Real.php', "<?php\nnamespace T;\nfinal class Real {}\n");
        file_put_contents($dir . '/notes.txt', "<?php\nnamespace T;\nfinal class Sneaky {}\n");
        file_put_contents($dir . '/legacy.inc', "<?php\nnamespace T;\nfinal class Legacy {}\n");

        self::assertSame(['T\\Real'], array_keys($this->scanner->scan([$dir])->getClasses()));
        self::assertCount(0, $this->scanner->scan([$dir . '/notes.txt']));
    }

    public function testImportsAliasesGroupsAndRelativeNamesResolveLikePhp(): void
    {
        $meta = $this->scanSource('Ctl', <<<'PHP'
            namespace App\Http;
            use App\Attr\{Route, Guard as G};
            use \Psr\Log\LoggerAwareInterface;
            use function App\helper;
            use Attribute;
            $f = function () use ($x) {};
            #[Route('/x'), G, Attribute, namespace\Local, Unimported, \Fq\Abs, attr\Nested]
            final class Ctl extends Base\Controller implements LoggerAwareInterface, \Countable {}
            PHP);

        self::assertSame(
            ['App\\Attr\\Route', 'App\\Attr\\Guard', 'Attribute', 'App\\Http\\Local', 'App\\Http\\Unimported', 'Fq\\Abs', 'App\\Http\\attr\\Nested'],
            array_keys($meta->attributes),
            'unimported names stay in the current namespace: classes never fall back to global',
        );
        self::assertSame('App\\Http\\Base\\Controller', $meta->parent);
        self::assertSame(['Psr\\Log\\LoggerAwareInterface', 'Countable'], $meta->interfaces);
    }

    public function testAliasesAreCaseInsensitiveAndImportsResetPerNamespace(): void
    {
        $meta = $this->scanSource('Second', <<<'PHP'
            namespace First;
            use Vendor\Attr\Route;
            namespace Second;
            use Vendor\Attr\Route as Path;
            #[path, Route]
            final class Second {}
            PHP);

        self::assertSame(['Vendor\\Attr\\Route', 'Second\\Route'], array_keys($meta->attributes));
    }

    public function testMixedGroupsSkipFunctionAndConstImports(): void
    {
        $meta = $this->scanSource('Mixed', "namespace T;\nuse Lib\\{Model, function helper, const LIMIT};\n#[Model, helper]\nfinal class Mixed {}");

        self::assertSame(['Lib\\Model', 'T\\helper'], array_keys($meta->attributes));
    }

    public function testTokenModeNeverInstantiatesAttributes(): void
    {
        $attribute = $this->fixture('AttributedClass')->getAttribute(self::fixtureClass('SampleAttribute'));

        self::assertNotNull($attribute);
        self::assertNull($attribute->instance, 'token mode never loads, so it never instantiates');
    }

    private function fixture(string $shortName): ClassMetadata
    {
        $meta = $this->scanner->scan([self::fixturePath($shortName . '.php')])->getClass(self::fixtureClass($shortName));
        self::assertNotNull($meta, "Fixture {$shortName} was not discovered");

        return $meta;
    }

    private function scanSource(string $shortName, string $body): ClassMetadata
    {
        $file = $this->temporaryDirectory() . "/{$shortName}.php";
        file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n{$body}\n");

        $classes = array_values($this->scanner->scan([$file])->getClasses());
        self::assertCount(1, $classes, "Expected exactly one declaration in {$shortName}");

        return $classes[0];
    }
}
