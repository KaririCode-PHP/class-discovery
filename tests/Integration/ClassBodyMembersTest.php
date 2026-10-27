<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Enum\Visibility;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Scanner\FileScanner;
use KaririCode\ClassDiscovery\Tests\TemporaryDirectories;
use PHPUnit\Framework\TestCase;

/**
 * Member parsing in token mode, against PHP's own semantics (what Reflection reports).
 */
final class ClassBodyMembersTest extends TestCase
{
    use TemporaryDirectories;

    private const string MEMBERS = <<<'PHP'
        namespace T;
        use Lib\{Logged, Traced};
        use Lib\Concerns\{Timestamps, SoftDeletes};
        abstract class Members
        {
            use Timestamps, SoftDeletes;
            use \Lib\Concerns\Audits { audit as protected record; }

            #[Traced] #[Logged]
            public const LIMIT = 10;

            #[Traced]
            public static int $count = 0, $peak;

            protected array $matrix = [[1, 2], [3, 4]], $empty = [];

            private ?string $tight=null;

            #[Traced] #[Logged]
            abstract protected function template(): void;

            public function plain(): void {}

            final public static function make(): void {}

            public function __CONSTRUCT(#[Logged] #[Traced] private readonly int $id, public array $tags = ['a', 'b']) {}
        }
        PHP;

    public function testTraitListsAndAdaptationBlocksAreResolved(): void
    {
        self::assertSame(
            ['Lib\\Concerns\\Timestamps', 'Lib\\Concerns\\SoftDeletes', 'Lib\\Concerns\\Audits'],
            $this->members()->traits,
        );
    }

    public function testMethodsCarryModifiersAndEveryAttributeGroup(): void
    {
        $methods = $this->members()->methods;

        self::assertSame(['template', 'plain', 'make', '__CONSTRUCT'], array_keys($methods));
        self::assertSame(Visibility::Protected, $methods['template']->visibility);
        self::assertTrue($methods['template']->isAbstract);
        self::assertSame(['Lib\\Traced', 'Lib\\Logged'], array_keys($methods['template']->attributes));
        self::assertFalse($methods['plain']->isAbstract);
        self::assertFalse($methods['plain']->isStatic);
        self::assertSame([], $methods['plain']->attributes);
        self::assertTrue($methods['make']->isStatic);
        self::assertSame(Visibility::Public, $methods['make']->visibility);
    }

    public function testConstantsAndTheirAttributesAreNotMembersOfTheNextDeclaration(): void
    {
        $meta = $this->members();

        self::assertArrayNotHasKey('LIMIT', $meta->properties);
        self::assertSame(['Lib\\Traced'], array_keys($meta->properties['count']->attributes));
    }

    public function testMultiPropertyDeclarationsShareModifiersAndAttributes(): void
    {
        $properties = $this->members()->properties;

        foreach (['count', 'peak'] as $name) {
            self::assertTrue($properties[$name]->isStatic, $name);
            self::assertSame(Visibility::Public, $properties[$name]->visibility, $name);
            self::assertSame(['Lib\\Traced'], array_keys($properties[$name]->attributes), $name);
        }
        self::assertTrue($properties['count']->hasDefault);
        self::assertFalse($properties['peak']->hasDefault);
    }

    public function testDefaultsWithNestedCommasDoNotSplitTheDeclaration(): void
    {
        $properties = $this->members()->properties;

        self::assertSame(Visibility::Protected, $properties['matrix']->visibility);
        self::assertTrue($properties['matrix']->hasDefault);
        self::assertSame(Visibility::Protected, $properties['empty']->visibility);
        self::assertTrue($properties['empty']->hasDefault);
        self::assertTrue($properties['tight']->hasDefault, 'a default without spaces around =');
        self::assertSame(Visibility::Private, $properties['tight']->visibility);
    }

    public function testConstructorNameIsCaseInsensitiveAndPromotesItsParameters(): void
    {
        $properties = $this->members()->properties;

        self::assertTrue($properties['id']->isPromoted);
        self::assertTrue($properties['id']->isReadonly);
        self::assertSame(Visibility::Private, $properties['id']->visibility);
        self::assertSame(['Lib\\Logged', 'Lib\\Traced'], array_keys($properties['id']->attributes));
        self::assertTrue($properties['tags']->isPromoted);
        self::assertFalse($properties['tags']->isReadonly);
        self::assertSame(Visibility::Public, $properties['tags']->visibility);
    }

    public function testReadonlyClassMakesPromotedPropertiesReadonly(): void
    {
        $readonly = $this->scanSource('Value', "namespace T;\nfinal readonly class Value\n{\n    public function __construct(public int \$a, private string \$b,) {}\n}");
        $plain = $this->scanSource('Plain', "namespace T;\nfinal class Plain\n{\n    public function __construct(private int \$x) {}\n}");

        self::assertSame(['a', 'b'], array_keys($readonly->properties), 'a trailing comma adds no parameter');
        self::assertTrue($readonly->properties['a']->isReadonly);
        self::assertTrue($readonly->properties['b']->isReadonly);
        self::assertTrue($plain->properties['x']->isPromoted, 'first parameter right after the parenthesis');
        self::assertFalse($plain->properties['x']->isReadonly);
    }

    public function testInterfaceMethodsAreAbstractLikeInReflection(): void
    {
        $port = $this->scanSource('Port', "namespace T;\ninterface Port\n{\n    public function send(): void;\n}");

        self::assertTrue($port->methods['send']->isAbstract);
    }

    public function testEnumCasesAndTheirAttributesStayOutOfMembers(): void
    {
        $enum = $this->scanSource('Status', "namespace T;\nenum Status\n{\n    #[Label]\n    case On;\n\n    public function label(): string { return 'on'; }\n}");

        self::assertSame(['label'], array_keys($enum->methods));
        self::assertSame([], $enum->methods['label']->attributes);
        self::assertSame([], $enum->properties);
    }

    public function testTruncatedBodyNeitherCrashesNorWarns(): void
    {
        $broken = $this->scanSource('Broken', "namespace T;\nfinal class Broken\n{\n    public function run(): void\n    {\n        \$x = [1, 2");

        self::assertSame('T\\Broken', $broken->name);
    }

    private function members(): ClassMetadata
    {
        return $this->scanSource('Members', self::MEMBERS);
    }

    private function scanSource(string $shortName, string $body): ClassMetadata
    {
        $file = $this->temporaryDirectory() . "/{$shortName}.php";
        file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n{$body}\n");

        $classes = array_values(new FileScanner()->scan([$file])->getClasses());
        self::assertCount(1, $classes);

        return $classes[0];
    }
}
