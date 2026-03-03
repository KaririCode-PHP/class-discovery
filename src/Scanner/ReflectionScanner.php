<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Contract\{
    CacheStrategy,
    ClassFilter,
    DiscoveryResult as DiscoveryResultContract,
    NamespaceResolver,
    Scanner as ScannerContract,
};
use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Result\{
    AttributeMetadata,
    ClassMetadata,
    DiscoveryResult,
    MethodMetadata,
    PropertyMetadata,
};

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
 * @package KaririCode\ClassDiscovery\Scanner
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   3.0.0
 */
final class ReflectionScanner implements ScannerContract
{
    /** @var array<ClassFilter> */
    private array $filters = [];

    private ?CacheStrategy $cache = null;

    public function __construct(
        private readonly NamespaceResolver $namespaceResolver,
    ) {
    }

    #[\Override]
    public function scan(array $paths): DiscoveryResultContract
    {
        $cacheKey = $this->computeCacheKey($paths);

        if ($this->cache !== null && $this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $startTime = hrtime(true);
        $classes = [];
        $errors = [];

        // First, use FileScanner to find class names
        $fileScanner = new FileScanner($this->namespaceResolver);
        $fileResult = $fileScanner->scan($paths);

        // Then, use reflection for rich metadata
        foreach ($fileResult->getClasses() as $fqcn => $basicMetadata) {
            try {
                if (! class_exists($fqcn) && ! interface_exists($fqcn) && ! enum_exists($fqcn)) {
                    continue;
                }

                $reflectionClass = new \ReflectionClass($fqcn);
                $metadata = $this->buildMetadataFromReflection($reflectionClass);

                if ($this->passesFilters($metadata)) {
                    $classes[$fqcn] = $metadata;
                }
            } catch (\ReflectionException $e) {
                $errors[$fqcn] = $e->getMessage();
            }
        }

        $duration = (float) (hrtime(true) - $startTime) / 1e9;
        $result = new DiscoveryResult($classes, $errors, $duration);

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $result);
        }

        return $result;
    }

    #[\Override]
    public function addFilter(ClassFilter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    #[\Override]
    public function setCacheStrategy(CacheStrategy $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    #[\Override]
    public function clearCache(): void
    {
        $this->cache?->clear();
    }

    private function buildMetadataFromReflection(\ReflectionClass $ref): ClassMetadata
    {
        $attributes = [];
        foreach ($ref->getAttributes() as $attr) {
            $attributes[$attr->getName()] = new AttributeMetadata(
                name: $attr->getName(),
                target: AttributeTarget::Class_,
                arguments: $attr->getArguments(),
                isRepeated: $attr->isRepeated(),
            );
        }

        $methods = [];
        foreach ($ref->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }

            $methodAttrs = [];
            foreach ($method->getAttributes() as $attr) {
                $methodAttrs[$attr->getName()] = new AttributeMetadata(
                    name: $attr->getName(),
                    target: AttributeTarget::Method,
                    arguments: $attr->getArguments(),
                    isRepeated: $attr->isRepeated(),
                    targetName: $method->getName(),
                );
            }

            $params = [];
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                $params[$param->getName()] = $type instanceof \ReflectionNamedType
                    ? $type->getName()
                    : ($type !== null ? (string) $type : 'mixed');
            }

            $returnType = $method->getReturnType();

            $methods[$method->getName()] = new MethodMetadata(
                name: $method->getName(),
                visibility: $method->isPublic() ? 'public' : ($method->isProtected() ? 'protected' : 'private'),
                isStatic: $method->isStatic(),
                isAbstract: $method->isAbstract(),
                returnType: $returnType instanceof \ReflectionNamedType ? $returnType->getName() : null,
                attributes: $methodAttrs,
                parameters: $params,
            );
        }

        $properties = [];
        foreach ($ref->getProperties() as $prop) {
            if ($prop->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }

            $propAttrs = [];
            foreach ($prop->getAttributes() as $attr) {
                $propAttrs[$attr->getName()] = new AttributeMetadata(
                    name: $attr->getName(),
                    target: AttributeTarget::Property,
                    arguments: $attr->getArguments(),
                    isRepeated: $attr->isRepeated(),
                    targetName: $prop->getName(),
                );
            }

            $type = $prop->getType();

            $properties[$prop->getName()] = new PropertyMetadata(
                name: $prop->getName(),
                visibility: $prop->isPublic() ? 'public' : ($prop->isProtected() ? 'protected' : 'private'),
                type: $type instanceof \ReflectionNamedType ? $type->getName() : null,
                isReadonly: $prop->isReadonly(),
                isStatic: $prop->isStatic(),
                hasDefault: $prop->hasDefaultValue(),
                isPromoted: $prop->isPromoted(),
                attributes: $propAttrs,
            );
        }

        $interfaces = array_values(array_map(
            static fn (\ReflectionClass $iface): string => $iface->getName(),
            $ref->getInterfaces(),
        ));

        $traits = array_values(array_map(
            static fn (\ReflectionClass $trait): string => $trait->getName(),
            $ref->getTraits(),
        ));

        $parent = $ref->getParentClass();

        return new ClassMetadata(
            name: $ref->getName(),
            filePath: $ref->getFileName() ?: '',
            namespace: $ref->getNamespaceName(),
            shortName: $ref->getShortName(),
            isFinal: $ref->isFinal(),
            isAbstract: $ref->isAbstract(),
            isReadonly: $ref->isReadOnly(),
            isInterface: $ref->isInterface(),
            isEnum: $ref->isEnum(),
            isTrait: $ref->isTrait(),
            interfaces: $interfaces,
            parent: $parent !== false ? $parent->getName() : null,
            traits: $traits,
            attributes: $attributes,
            methods: $methods,
            properties: $properties,
        );
    }

    private function passesFilters(ClassMetadata $metadata): bool
    {
        foreach ($this->filters as $filter) {
            if (! $filter->accept($metadata)) {
                return false;
            }
        }

        return true;
    }

    private function computeCacheKey(array $paths): string
    {
        $parts = [
            self::class,
            implode('|', array_map('realpath', array_filter($paths, 'is_string'))),
            implode('|', array_map(static fn (ClassFilter $f): string => $f::class, $this->filters)),
        ];

        return hash('xxh128', implode("\0", $parts));
    }
}
