<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Integration;

use KaririCode\ClassDiscovery\Enum\Visibility;
use KaririCode\ClassDiscovery\Filter\AttributeFilter;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Scanner\AttributeScanner;
use KaririCode\ClassDiscovery\Scanner\FileScanner;
use KaririCode\ClassDiscovery\Tests\FixturePaths;
use KaririCode\ClassDiscovery\Tests\TemporaryDirectories;
use PHPUnit\Framework\TestCase;

/**
 * Token mode reads members and resolves names without loading the code.
 */
final class ClassBodyParsingTest extends TestCase
{
    use FixturePaths;
    use TemporaryDirectories;

    public function testMethodsCarryVisibilityModifiersAndResolvedAttributes(): void
    {
        $meta = $this->scan('Service', <<<'PHP'
            namespace App\Service;
            use App\Telemetry\Traced;
            use App\Telemetry as T;
            abstract class Service
            {
                #[Traced] public function handle(): void { $label = "{$this->name}-${suffix}"; }
                #[T\Metered, Traced] protected static function &count(): int { return 1; }
                abstract protected function template(): void;
                private function hidden(): string { $f = function () { return new class {}; }; return ''; }
            }
            PHP);

        self::assertSame(['handle', 'count', 'template', 'hidden'], array_keys($meta->methods));
        self::assertTrue($meta->methods['handle']->hasAttribute('App\\Telemetry\\Traced'));
        self::assertSame(['App\\Telemetry\\Metered', 'App\\Telemetry\\Traced'], array_keys($meta->methods['count']->attributes));
        self::assertSame(Visibility::Protected, $meta->methods['count']->visibility);
        self::assertTrue($meta->methods['count']->isStatic);
        self::assertTrue($meta->methods['template']->isAbstract);
        self::assertSame(Visibility::Private, $meta->methods['hidden']->visibility);
        self::assertSame([], $meta->methods['hidden']->attributes);
    }

    public function testDeclaredPromotedAndHookedProperties(): void
    {
        $meta = $this->scan('Entity', <<<'PHP'
            namespace App;
            use App\Orm\Column;
            final class Entity
            {
                #[Column] public int $a = 1, $b;
                protected static ?string $label = null;
                public string $computed { get => strtoupper($this->label ?? ''); }
                private array $tags = ['x', 'y'];
                public function __construct(
                    #[Column] private readonly int $id,
                    public string $name = 'n',
                    string $notPromoted = '',
                ) {}
            }
            PHP);

        self::assertSame(['a', 'b', 'label', 'computed', 'tags', 'id', 'name'], array_keys($meta->properties));
        self::assertTrue($meta->properties['b']->hasAttribute('App\\Orm\\Column'), 'one declaration, shared attributes');
        self::assertTrue($meta->properties['a']->hasDefault);
        self::assertTrue($meta->properties['label']->isStatic);
        self::assertSame(Visibility::Protected, $meta->properties['label']->visibility);
        self::assertTrue($meta->properties['id']->isPromoted);
        self::assertTrue($meta->properties['id']->isReadonly);
        self::assertTrue($meta->properties['id']->hasAttribute('App\\Orm\\Column'));
        self::assertArrayNotHasKey('notPromoted', $meta->properties);
    }

    public function testReadonlyClassMakesEveryPropertyReadonly(): void
    {
        $meta = $this->scan('Money', "namespace App;\nfinal readonly class Money\n{\n    public function __construct(public int \$amount) {}\n}");

        self::assertTrue($meta->properties['amount']->isReadonly);
    }

    public function testTraitsConstantsAndCasesAreHandled(): void
    {
        $meta = $this->scan('Status', <<<'PHP'
            namespace App;
            use App\Concern\HasLabel;
            enum Status: string
            {
                use HasLabel, \Other\Loggable { HasLabel::label insteadof Loggable; }
                const DEFAULT = self::On;
                #[Deprecated] case On = 'on';
                case Off = 'off';
                public function isOn(): bool { return $this === self::On; }
            }
            PHP);

        self::assertSame(['App\\Concern\\HasLabel', 'Other\\Loggable'], $meta->traits);
        self::assertSame(['isOn'], array_keys($meta->methods));
        self::assertSame([], $meta->methods['isOn']->attributes, 'attributes on cases do not leak to the next member');
    }

    public function testInterfaceMethodsAreAbstract(): void
    {
        $meta = $this->scan('Contract', "namespace App;\ninterface Contract\n{\n    public function handle(): void;\n}");

        self::assertTrue($meta->methods['handle']->isAbstract);
    }

    public function testAttributeFilterMatchesMemberAttributesInTokenMode(): void
    {
        $file = $this->write('Controller', "namespace App;\nuse Framework\\Route;\nfinal class Controller\n{\n    #[Route('/home')] public function index(): void {}\n}");

        $scanner = $this->fileScanner()->addFilter(new AttributeFilter('Framework\\Route'));

        self::assertSame(['App\\Controller'], array_keys($scanner->scan([$file])->getClasses()));
    }

    public function testDiscoverWorksAsTheClassFinderClosureConsumersTake(): void
    {
        // The shape Observability takes: \Closure(string $attribute, list<string> $directories): list<string>.
        $dir = $this->temporaryDirectory();
        file_put_contents($dir . '/Orders.php', "<?php\nnamespace App;\nuse App\\Telemetry\\Traced;\nfinal class Orders\n{\n    #[Traced] public function place(): void {}\n}\n");
        file_put_contents($dir . '/Plain.php', "<?php\nnamespace App;\nfinal class Plain {}\n");

        $finder = new AttributeScanner()->discover(...);

        self::assertSame(['App\\Orders'], $finder('App\\Telemetry\\Traced', [$dir]));
    }

    private function scan(string $shortName, string $body): ClassMetadata
    {
        $classes = array_values($this->fileScanner()->scan([$this->write($shortName, $body)])->getClasses());
        self::assertCount(1, $classes);

        return $classes[0];
    }

    private function write(string $shortName, string $body): string
    {
        $file = $this->temporaryDirectory() . "/{$shortName}.php";
        file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n{$body}\n");

        return $file;
    }

    private function fileScanner(): FileScanner
    {
        return new FileScanner();
    }
}
