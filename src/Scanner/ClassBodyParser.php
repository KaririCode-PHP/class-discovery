<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

use KaririCode\ClassDiscovery\Enum\AttributeTarget;
use KaririCode\ClassDiscovery\Enum\Visibility;
use KaririCode\ClassDiscovery\Result\AttributeMetadata;
use KaririCode\ClassDiscovery\Result\MethodMetadata;
use KaririCode\ClassDiscovery\Result\PropertyMetadata;

/**
 * Reads the members of one class-like body from its tokens: methods, declared
 * and constructor-promoted properties, trait uses, and the attributes of each.
 * This is what lets token mode find a class by an attribute on a method —
 * `#[Route]` on an action, `#[Traced]` on a service method — without loading it.
 *
 * Token mode records what the source states: names, visibility, `static`,
 * `abstract`, `readonly` and resolved attribute names. Declared types stay with
 * ReflectionScanner — methods carry no parameters or return type here, and
 * properties no type. Trait members are not copied into the using class.
 *
 * ```php
 * $members = new ClassBodyParser($stream, $imports, readonlyClass: false, interface: false)
 *     ->parse($openingBraceIndex);
 * $members['methods']['handle']->hasAttribute('App\\Attribute\\Traced');
 * ```
 *
 * @internal Used by FileScanner; one instance parses one body.
 */
final class ClassBodyParser
{
    private const array MODIFIERS = [
        T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL, T_READONLY, T_VAR,
        T_PUBLIC_SET, T_PROTECTED_SET, T_PRIVATE_SET,
    ];

    /** @var array<string, MethodMetadata> */
    private array $methods = [];

    /** @var array<string, PropertyMetadata> */
    private array $properties = [];

    /** @var list<string> */
    private array $traits = [];

    /** @var list<string> Attributes waiting for the member they decorate */
    private array $attributes = [];

    /** @var list<int> Modifiers waiting for the member they qualify */
    private array $modifiers = [];

    public function __construct(
        private readonly TokenStream $stream,
        private readonly ImportTable $imports,
        private readonly bool $readonlyClass,
        private readonly bool $interface,
    ) {}

    /**
     * Identifiers → metadata, with repeated identifiers flagged.
     *
     * @param list<string> $names Fully qualified attribute names
     *
     * @return array<string, AttributeMetadata>
     */
    public static function attributesFrom(array $names, AttributeTarget $target, ?string $targetName = null): array
    {
        $attributes = [];
        foreach (array_count_values($names) as $name => $occurrences) {
            $attributes[$name] = new AttributeMetadata(
                name: $name,
                target: $target,
                isRepeated: $occurrences > 1,
                targetName: $targetName,
            );
        }

        return $attributes;
    }

    /**
     * @return array{methods: array<string, MethodMetadata>, properties: array<string, PropertyMetadata>, traits: list<string>}
     */
    public function parse(int $open): array
    {
        for ($i = $open + 1; isset($this->stream->tokens[$i]) && !$this->stream->tokens[$i]->is('}'); $i++) {
            $i = $this->member($i);
        }

        return ['methods' => $this->methods, 'properties' => $this->properties, 'traits' => $this->traits];
    }

    private function member(int $i): int
    {
        $token = $this->stream->tokens[$i];

        return match (true) {
            $token->is(T_ATTRIBUTE) => $this->attributeGroup($i),
            $token->is(self::MODIFIERS) => $this->modifier($i),
            $token->is(T_FUNCTION) => $this->method($i),
            $token->is(T_VARIABLE) => $this->property($i),
            $token->is(T_USE) => $this->traitUse($i),
            $token->is([T_CONST, T_CASE]) => $this->reset($this->stream->declarationEnd($i)),
            $token->is(';') => $this->reset($i),
            default => $i,
        };
    }

    private function attributeGroup(int $open): int
    {
        [$names, $close] = $this->stream->attributeGroup($open);
        $this->attributes = [...$this->attributes, ...array_map($this->imports->resolve(...), $names)];

        return $close;
    }

    private function modifier(int $i): int
    {
        $this->modifiers[] = $this->stream->tokens[$i]->id;

        return $i;
    }

    private function method(int $function): int
    {
        $nameIndex = (int) $this->stream->significantIndex($function, 1);
        if ($this->stream->tokens[$nameIndex]->text === '&') {
            $nameIndex = (int) $this->stream->significantIndex($nameIndex, 1);
        }

        $name = $this->stream->tokens[$nameIndex]->text;
        $open = (int) $this->stream->significantIndex($nameIndex, 1);
        $close = $this->stream->closing($open);

        if (strtolower($name) === '__construct') {
            foreach ($this->stream->commaSeparated($open, $close) as [$start, $end]) {
                $this->promotedProperty($start, $end);
            }
        }

        $this->methods[$name] = new MethodMetadata(
            name: $name,
            visibility: self::visibility($this->modifiers),
            isStatic: in_array(T_STATIC, $this->modifiers, true),
            isAbstract: $this->interface || in_array(T_ABSTRACT, $this->modifiers, true),
            attributes: self::attributesFrom($this->attributes, AttributeTarget::Method, $name),
        );

        return $this->reset($this->stream->declarationEnd($close));
    }

    /**
     * A constructor parameter with a visibility or `readonly` modifier declares a property.
     */
    private function promotedProperty(int $start, int $end): void
    {
        $attributes = [];
        $modifiers = [];

        for ($i = $start; $i <= $end; $i++) {
            $token = $this->stream->tokens[$i];
            if ($token->is(T_ATTRIBUTE)) {
                [$names, $i] = $this->stream->attributeGroup($i);
                $attributes = [...$attributes, ...array_map($this->imports->resolve(...), $names)];
            } elseif ($token->is(self::MODIFIERS)) {
                $modifiers[] = $token->id;
            } elseif ($token->is(T_VARIABLE)) {
                $this->addPromoted(substr($token->text, 1), $modifiers, $attributes);

                return;
            }
        }
    }

    /**
     * @param list<int>    $modifiers
     * @param list<string> $attributes
     */
    private function addPromoted(string $name, array $modifiers, array $attributes): void
    {
        if ($modifiers === []) {
            return;
        }

        $this->properties[$name] = new PropertyMetadata(
            name: $name,
            visibility: self::visibility($modifiers),
            isReadonly: $this->readonlyClass || in_array(T_READONLY, $modifiers, true),
            isPromoted: true,
            attributes: self::attributesFrom($attributes, AttributeTarget::Property, $name),
        );
    }

    /**
     * A declared property; `public int $a = 1, $b;` declares two, sharing modifiers
     * and attributes. A hook block `{ get => ...; }` ends the declaration.
     */
    private function property(int $variable): int
    {
        $name = substr($this->stream->tokens[$variable]->text, 1);
        $next = $this->stream->significantIndex($variable, 1);

        $this->properties[$name] = new PropertyMetadata(
            name: $name,
            visibility: self::visibility($this->modifiers),
            isReadonly: $this->readonlyClass || in_array(T_READONLY, $this->modifiers, true),
            isStatic: in_array(T_STATIC, $this->modifiers, true),
            hasDefault: $next !== null && $this->stream->tokens[$next]->is('='),
            attributes: self::attributesFrom($this->attributes, AttributeTarget::Property, $name),
        );

        return $this->afterProperty($variable);
    }

    private function afterProperty(int $variable): int
    {
        $depth = 0;
        for ($i = $variable + 1; isset($this->stream->tokens[$i]); $i++) {
            $token = $this->stream->tokens[$i];
            if ($depth === 0 && $token->is([',', ';', '{', '}'])) {
                return match ($token->text) {
                    ',' => $i,
                    ';' => $this->reset($i),
                    '{' => $this->reset($this->stream->closing($i)),
                    default => $i - 1,
                };
            }

            $depth += $this->stream->nestingDelta($token);
        }

        return count($this->stream->tokens) - 1;
    }

    private function traitUse(int $use): int
    {
        for ($i = $use + 1; isset($this->stream->tokens[$i]); $i++) {
            $token = $this->stream->tokens[$i];
            if ($token->is(TokenStream::NAME_TOKENS)) {
                $this->traits[] = $this->imports->resolve($token->text);
            } elseif ($token->is([';', '{'])) {
                return $this->reset($token->is('{') ? $this->stream->closing($i) : $i);
            }
        }

        return count($this->stream->tokens) - 1;
    }

    private function reset(int $last): int
    {
        $this->attributes = [];
        $this->modifiers = [];

        return $last;
    }

    /**
     * @param list<int> $modifiers
     */
    private static function visibility(array $modifiers): Visibility
    {
        return match (true) {
            in_array(T_PRIVATE, $modifiers, true) => Visibility::Private,
            in_array(T_PROTECTED, $modifiers, true) => Visibility::Protected,
            default => Visibility::Public,
        };
    }
}
