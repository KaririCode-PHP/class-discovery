<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Contract\CacheStrategy;
use KaririCode\ClassDiscovery\Contract\ClassFilter;
use KaririCode\ClassDiscovery\Contract\DiscoveryResult as DiscoveryResultContract;
use KaririCode\ClassDiscovery\Contract\Scanner as ScannerContract;
use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Enum\Visibility;
use KaririCode\ClassDiscovery\Result\AttributeMetadata;
use KaririCode\ClassDiscovery\Result\ClassMetadata;
use KaririCode\ClassDiscovery\Result\DiscoveryResult;
use KaririCode\ClassDiscovery\Result\MethodMetadata;
use KaririCode\ClassDiscovery\Result\PropertyMetadata;

/**
 * Reflection-based scanner for runtime class introspection.
 *
 * Unlike FileScanner, this scanner requires classes to be loaded
 * (via autoloader). It provides richer metadata including resolved
 * attribute arguments and complete type information.
 *
 * ## When to Use
 *
 * Use ReflectionScanner when you need fully resolved attribute arguments
 * or complete type hierarchy. Use FileScanner/AttributeScanner for
 * faster discovery without class loading.
 *
 * ## Trust boundary
 *
 * Autoloading *executes* the scanned files. Point this scanner at trusted code
 * only. A class whose loading fails (missing parent, parse error, fatal in file
 * scope) is reported through DiscoveryResult::getErrors() instead of aborting
 * the scan.
 *
 * ```php
 * $meta = new ReflectionScanner()
 *     ->scan(['src/'])
 *     ->getClass(HomeController::class);
 * $meta?->methods['index']->getAttribute(Route::class)?->arguments; // resolved values
 * ```
 */
final class ReflectionScanner implements ScannerContract
{
    /** @var list<ClassFilter> */
    private array $filters = [];
    private ?CacheStrategy $cache = null;

    public function scan(array $paths): DiscoveryResultContract
    {
        $cacheKey = ScanCacheKey::of(self::class, $paths, $this->filters)->value;
        $cached = $this->cache?->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $startTime = hrtime(true);
        $classes = [];
        $errors = [];

        $declared = new FileScanner()->scan($paths)->getClasses();

        foreach (array_keys($declared) as $fqcn) {
            try {
                $metadata = $this->reflect($fqcn);
            } catch (\ReflectionException|\Error $e) {
                $errors[$fqcn] = $e->getMessage();

                continue;
            }

            if ($metadata !== null && $this->passesFilters($metadata)) {
                $classes[$fqcn] = $metadata;
            }
        }

        $result = new DiscoveryResult($classes, $errors, (float) (hrtime(true) - $startTime) / 1e9);
        $this->cache?->set($cacheKey, $result);

        return $result;
    }

    public function addFilter(ClassFilter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    public function setCacheStrategy(CacheStrategy $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    public function clearCache(): void
    {
        $this->cache?->clear();
    }

    /**
     * Loads the declared type through the autoloader; null when it cannot be found.
     *
     * @throws \ReflectionException
     * @throws \Error When autoloading the file fails (missing parent, parse error, fatal in file scope)
     */
    private function reflect(string $fqcn): ?ClassMetadata
    {
        // class_exists() also covers enums; traits need their own probe.
        if (!class_exists($fqcn) && !interface_exists($fqcn) && !trait_exists($fqcn)) {
            return null;
        }

        return $this->buildMetadata(new \ReflectionClass($fqcn));
    }

    /**
     * @param \ReflectionClass<object> $ref
     */
    private function buildMetadata(\ReflectionClass $ref): ClassMetadata
    {
        $parent = $ref->getParentClass();
        $fileName = $ref->getFileName();

        return new ClassMetadata(
            name: $ref->getName(),
            filePath: $fileName === false ? '' : $fileName,
            namespace: $ref->getNamespaceName(),
            shortName: $ref->getShortName(),
            isFinal: $ref->isFinal(),
            isAbstract: $ref->isAbstract(),
            isReadonly: $ref->isReadOnly(),
            isInterface: $ref->isInterface(),
            isEnum: $ref->isEnum(),
            isTrait: $ref->isTrait(),
            interfaces: $ref->getInterfaceNames(),
            parent: $parent === false ? null : $parent->getName(),
            traits: $ref->getTraitNames(),
            attributes: $this->attributes($ref->getAttributes(), AttributeTarget::Class_),
            methods: $this->methods($ref),
            properties: $this->properties($ref),
        );
    }

    /**
     * @param \ReflectionClass<object> $ref
     *
     * @return array<string, MethodMetadata>
     */
    private function methods(\ReflectionClass $ref): array
    {
        $methods = [];
        foreach ($ref->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }

            $returnType = $method->getReturnType();
            $methods[$method->getName()] = new MethodMetadata(
                name: $method->getName(),
                visibility: self::visibility($method),
                isStatic: $method->isStatic(),
                isAbstract: $method->isAbstract(),
                returnType: $returnType instanceof \ReflectionNamedType ? $returnType->getName() : null,
                attributes: $this->attributes($method->getAttributes(), AttributeTarget::Method, $method->getName()),
                parameters: self::parameterTypes($method),
            );
        }

        return $methods;
    }

    /**
     * @param \ReflectionClass<object> $ref
     *
     * @return array<string, PropertyMetadata>
     */
    private function properties(\ReflectionClass $ref): array
    {
        $properties = [];
        foreach ($ref->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }

            $type = $property->getType();
            $properties[$property->getName()] = new PropertyMetadata(
                name: $property->getName(),
                visibility: self::visibility($property),
                type: $type instanceof \ReflectionNamedType ? $type->getName() : null,
                isReadonly: $property->isReadOnly(),
                isStatic: $property->isStatic(),
                hasDefault: $property->hasDefaultValue(),
                isPromoted: $property->isPromoted(),
                attributes: $this->attributes($property->getAttributes(), AttributeTarget::Property, $property->getName()),
            );
        }

        return $properties;
    }

    /**
     * @param array<\ReflectionAttribute<object>> $reflected
     *
     * @return array<string, AttributeMetadata>
     */
    private function attributes(array $reflected, AttributeTarget $target, ?string $targetName = null): array
    {
        $attributes = [];
        foreach ($reflected as $attribute) {
            $attributes[$attribute->getName()] = new AttributeMetadata(
                name: $attribute->getName(),
                target: $target,
                arguments: $attribute->getArguments(),
                isRepeated: $attribute->isRepeated(),
                targetName: $targetName,
                instance: self::instantiate($attribute),
            );
        }

        return $attributes;
    }

    /**
     * The attribute object, as PHP builds it on demand. Instantiation validates
     * the attribute's target and repeatability and runs its constructor, so it
     * can fail where reading the arguments did not; the metadata keeps the
     * arguments either way and reports no instance.
     *
     * @param \ReflectionAttribute<object> $attribute
     */
    private static function instantiate(\ReflectionAttribute $attribute): ?object
    {
        try {
            return $attribute->newInstance();
        } catch (\Throwable) {
            return null; // e.g. TARGET_CLASS attribute on a method, or a constructor that throws
        }
    }

    /**
     * @return array<string, string> Parameter name => declared type ('mixed' when untyped)
     */
    private static function parameterTypes(\ReflectionMethod $method): array
    {
        $types = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $types[$parameter->getName()] = match (true) {
                $type instanceof \ReflectionNamedType => $type->getName(),
                $type !== null => (string) $type,
                default => 'mixed',
            };
        }

        return $types;
    }

    private static function visibility(\ReflectionMethod|\ReflectionProperty $member): Visibility
    {
        return match (true) {
            $member->isPublic() => Visibility::Public,
            $member->isProtected() => Visibility::Protected,
            default => Visibility::Private,
        };
    }

    private function passesFilters(ClassMetadata $metadata): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->accept($metadata)) {
                return false;
            }
        }

        return true;
    }
}
