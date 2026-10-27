<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Scanner;

use KaririCode\ClassDiscovery\Scanner\ImportTable;
use KaririCode\ClassDiscovery\Scanner\TokenStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportTableTest extends TestCase
{
    /**
     * PHP's rules for class references (php.net, "Name resolution rules").
     */
    #[DataProvider('resolutions')]
    public function testResolvesLikePhp(string $name, string $expected): void
    {
        $imports = new ImportTable('App\\Http', ['route' => 'Framework\\Routing\\Route', 'orm' => 'Vendor\\Orm']);

        self::assertSame($expected, $imports->resolve($name));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function resolutions(): \Generator
    {
        yield 'fully qualified stands' => ['\\Attribute', 'Attribute'];
        yield 'imported alias' => ['Route', 'Framework\\Routing\\Route'];
        yield 'alias is case-insensitive' => ['ROUTE', 'Framework\\Routing\\Route'];
        yield 'qualified through an alias' => ['Orm\\Entity', 'Vendor\\Orm\\Entity'];
        yield 'unqualified, not imported' => ['Controller', 'App\\Http\\Controller'];
        yield 'qualified, not imported' => ['Sub\\Thing', 'App\\Http\\Sub\\Thing'];
        yield 'relative to the namespace' => ['namespace\\Thing', 'App\\Http\\Thing'];
    }

    public function testGlobalNamespaceLeavesNamesUnqualified(): void
    {
        self::assertSame('Controller', new ImportTable()->resolve('Controller'));
    }

    public function testReadsEveryImportFormBeforeTheDeclaration(): void
    {
        $imports = $this->tableFor(<<<'PHP'
            <?php
            namespace App;
            use Framework\Routing\Route;
            use \Vendor\Lib\Thing as Aliased, Other\Single;
            use Group\Pkg\{First, Second as Renamed, function helper, const LIMIT};
            use function strlen;
            use const PHP_EOL;
            $closure = function () use ($x) {};
            final class Target {}
            PHP);

        self::assertSame('App', $imports->namespace);
        self::assertSame('Framework\\Routing\\Route', $imports->resolve('Route'));
        self::assertSame('Vendor\\Lib\\Thing', $imports->resolve('Aliased'));
        self::assertSame('Other\\Single', $imports->resolve('Single'));
        self::assertSame('Group\\Pkg\\First', $imports->resolve('First'));
        self::assertSame('Group\\Pkg\\Second', $imports->resolve('Renamed'));
        self::assertSame('App\\helper', $imports->resolve('helper'), 'function imports never alias classes');
        self::assertSame('App\\x', $imports->resolve('x'), "a closure's use() imports nothing");
    }

    public function testANewNamespaceStartsAFreshScope(): void
    {
        $imports = $this->tableFor("<?php\nnamespace First;\nuse A\\Imported;\nnamespace Second;\nfinal class Target {}");

        self::assertSame('Second', $imports->namespace);
        self::assertSame('Second\\Imported', $imports->resolve('Imported'));
    }

    private function tableFor(string $source): ImportTable
    {
        $stream = TokenStream::of($source);
        $declaration = null;
        foreach ($stream->tokens as $index => $token) {
            if ($token->is(T_CLASS)) {
                $declaration = $index;
            }
        }

        self::assertNotNull($declaration);

        return ImportTable::at($stream, $declaration);
    }
}
