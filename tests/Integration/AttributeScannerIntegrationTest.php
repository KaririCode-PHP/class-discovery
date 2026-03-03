<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Contract\NamespaceResolver;
use KaririCode\ClassDiscovery\Scanner\AttributeScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeScanner::class)]
final class AttributeScannerIntegrationTest extends TestCase
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

    public function testScanReturnsResults(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $result = $scanner->scan([$this->fixtureDir]);

        self::assertGreaterThan(0, $result->count());
    }

    public function testScanForAttributeFindsAnnotatedClass(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $result = $scanner->scanForAttribute(
            'SampleAttribute',  // token parser stores short names
            [$this->fixtureDir],
        );

        // AttributedClass has #[SampleAttribute]
        $found = false;
        foreach ($result as $fqcn => $meta) {
            if (str_contains($fqcn, 'AttributedClass')) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, 'AttributedClass should be found by SampleAttribute filter');
    }

    public function testScanForAttributesOrLogic(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $result = $scanner->scanForAttributes(
            [
                'SampleAttribute',          // token parser short name
                'NonExistentAttribute',     // does not exist
            ],
            [$this->fixtureDir],
        );

        // Should find at least AttributedClass (has SampleAttribute)
        self::assertGreaterThan(0, $result->count());
    }

    public function testScanForNonexistentAttributeReturnsEmpty(): void
    {
        $scanner = new AttributeScanner($this->resolver);
        $result = $scanner->scanForAttribute(
            'TrulyNonExistentAttributeXyz',  // cannot match any short name in fixtures
            [$this->fixtureDir],
        );

        self::assertCount(0, $result);
    }

    public function testCompositionDoesNotDoubleRead(): void
    {
        // Verify the composition pattern works (BUG-006 fix)
        $scanner = new AttributeScanner($this->resolver);

        $result1 = $scanner->scan([$this->fixtureDir]);
        // Scan again — should work without issues
        $scanner2 = new AttributeScanner($this->resolver);
        $result2 = $scanner2->scan([$this->fixtureDir]);

        self::assertSame($result1->count(), $result2->count());
    }
}
