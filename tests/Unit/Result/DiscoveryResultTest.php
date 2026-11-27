<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Tests\Unit\Result;

use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult as DiscoveryResultContract;
use KaririCode\ClassDiscovery\Result\{ClassMetadata, DiscoveryResult};
use KaririCode\ClassDiscovery\Tests\MetadataFactory;
use PHPUnit\Framework\TestCase;

final class DiscoveryResultTest extends TestCase
{
    use MetadataFactory;

    public function testEmptyResult(): void
    {
        $result = new DiscoveryResult();

        self::assertSame([], $result->getClasses());
        self::assertCount(0, $result);
        self::assertFalse($result->hasErrors());
        self::assertSame([], $result->getErrors());
        self::assertSame(0.0, $result->getScanDuration());
    }

    public function testResultWithClasses(): void
    {
        $classA = self::createClassMetadata(name: 'App\\A');
        $classB = self::createClassMetadata(name: 'App\\B');

        $result = new DiscoveryResult(
            ['App\\A' => $classA, 'App\\B' => $classB],
            [],
            0.05,
        );

        self::assertCount(2, $result);
        self::assertTrue($result->hasClass('App\\A'));
        self::assertTrue($result->hasClass('App\\B'));
        self::assertFalse($result->hasClass('App\\C'));
        self::assertSame($classA, $result->getClass('App\\A'));
        self::assertNull($result->getClass('App\\C'));
        self::assertSame(0.05, $result->getScanDuration());
    }

    public function testResultWithErrors(): void
    {
        $result = new DiscoveryResult(
            [],
            ['/bad/file.php' => 'Parse error'],
            0.01,
        );

        self::assertTrue($result->hasErrors());
        self::assertSame(['/bad/file.php' => 'Parse error'], $result->getErrors());
    }

    public function testFilter(): void
    {
        $final = self::createClassMetadata(name: 'App\\Final', isFinal: true);
        $nonFinal = self::createClassMetadata(name: 'App\\Open', isFinal: false);

        $result = new DiscoveryResult(
            ['App\\Final' => $final, 'App\\Open' => $nonFinal],
        );

        $filter = new class implements ClassFilter {
            public function accept(ClassMetadata $metadata): bool
            {
                return $metadata->isFinal;
            }
        };

        $filtered = $result->filter($filter);

        self::assertNotSame($result, $filtered); // Immutable — new instance
        self::assertCount(2, $result);            // Original untouched
        self::assertCount(1, $filtered);
        self::assertTrue($filtered->hasClass('App\\Final'));
        self::assertFalse($filtered->hasClass('App\\Open'));
    }

    public function testMerge(): void
    {
        $classA = self::createClassMetadata(name: 'App\\A');
        $classB = self::createClassMetadata(name: 'App\\B');

        $resultA = new DiscoveryResult(['App\\A' => $classA], [], 0.02);
        $resultB = new DiscoveryResult(['App\\B' => $classB], ['/err' => 'fail'], 0.03);

        $merged = $resultA->merge($resultB);

        self::assertNotSame($resultA, $merged);
        self::assertCount(2, $merged);
        self::assertTrue($merged->hasClass('App\\A'));
        self::assertTrue($merged->hasClass('App\\B'));
        self::assertSame(0.05, $merged->getScanDuration());
        self::assertTrue($merged->hasErrors());
    }

    public function testMergeWithContractType(): void
    {
        $classA = self::createClassMetadata(name: 'App\\A');
        $classB = self::createClassMetadata(name: 'App\\B');

        $resultA = new DiscoveryResult(['App\\A' => $classA], [], 0.01);
        $resultB = new DiscoveryResult(['App\\B' => $classB], [], 0.02);

        // Merge accepts DiscoveryResultContract — no longer crashes (BUG-003 fixed)
        $merged = $resultA->merge($resultB);
        self::assertCount(2, $merged);
    }

    public function testCountable(): void
    {
        $result = new DiscoveryResult([
            'A' => self::createClassMetadata(name: 'A'),
            'B' => self::createClassMetadata(name: 'B'),
            'C' => self::createClassMetadata(name: 'C'),
        ]);

        self::assertCount(3, $result); // Via Countable
    }

    public function testIteratorAggregate(): void
    {
        $classes = [
            'A' => self::createClassMetadata(name: 'A'),
            'B' => self::createClassMetadata(name: 'B'),
        ];
        $result = new DiscoveryResult($classes);

        $iterated = [];
        foreach ($result as $fqcn => $meta) {
            $iterated[$fqcn] = $meta;
        }

        self::assertSame($classes, $iterated);
    }

    public function testImmutability(): void
    {
        $ref = new \ReflectionClass(DiscoveryResult::class);

        self::assertTrue($ref->isReadonly());
        self::assertTrue($ref->isFinal());
    }
}
