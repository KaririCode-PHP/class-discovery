<?php

declare(strict_types=1);

namespace KaririCode\ClassDiscovery\Scanner;

/**
 * Navigation over one file's tokens: significant neighbours, bracket matching,
 * attribute groups and comma-separated lists.
 *
 * Every method that consumes tokens returns the index of the last token it
 * consumed, so a caller's loop increment lands on the first unconsumed one —
 * the invariant that keeps terminators from being skipped (ADR-001, addendum).
 *
 * @internal Shared by FileScanner, ImportTable and ClassBodyParser.
 */
final readonly class TokenStream
{
    /** Names as written in source: short, qualified, fully qualified, relative. */
    public const array NAME_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

    /** `#[` closes with `]`; the `{` of string interpolation closes with `}`. */
    private const array OPENERS = ['(', '[', '{', T_ATTRIBUTE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES];

    private const array CLOSERS = [')', ']', '}'];

    /**
     * @param list<\PhpToken> $tokens
     */
    public function __construct(
        public array $tokens,
    ) {}

    public static function of(string $source): self
    {
        /** @var list<\PhpToken> $tokens PhpToken::tokenize() returns a list; the stub declares array<static>. */
        $tokens = \PhpToken::tokenize($source);

        return new self($tokens);
    }

    /**
     * Index of the nearest non-whitespace, non-comment token in the given direction.
     *
     * @param -1|1 $direction
     */
    public function significantIndex(int $from, int $direction): ?int
    {
        for ($i = $from + $direction; isset($this->tokens[$i]); $i += $direction) {
            if (!$this->tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Index of the bracket closing the one at $open; the last index when the file is truncated.
     */
    public function closing(int $open): int
    {
        $depth = 0;
        for ($i = $open; isset($this->tokens[$i]); $i++) {
            $depth += $this->nestingDelta($this->tokens[$i]);
            if ($depth === 0) {
                return $i;
            }
        }

        return count($this->tokens) - 1;
    }

    /**
     * Index of the `;` ending the declaration that starts after $from, or of the
     * `}` closing its body — whichever comes first at the declaration's own level.
     */
    public function declarationEnd(int $from): int
    {
        $depth = 0;
        for ($i = $from + 1; isset($this->tokens[$i]); $i++) {
            $token = $this->tokens[$i];
            if ($depth === 0 && $token->is(';')) {
                return $i;
            }

            if ($depth === 0 && $token->is('{')) {
                return $this->closing($i);
            }

            $depth += $this->nestingDelta($token);
        }

        return count($this->tokens) - 1;
    }

    /**
     * One attribute group `#[A, B(...)]` from its `#[` token.
     *
     * @return array{0: list<string>, 1: int} top-level names as written, and the index of the closing `]`
     */
    public function attributeGroup(int $open): array
    {
        $close = $this->closing($open);
        $names = [];

        foreach ($this->commaSeparated($open, $close) as [$start, $end]) {
            $first = $this->significantIndex($start - 1, 1);
            if ($first !== null && $first <= $end && $this->tokens[$first]->is(self::NAME_TOKENS)) {
                $names[] = $this->tokens[$first]->text;
            }
        }

        return [$names, $close];
    }

    /**
     * Token ranges between the brackets at $open and $close, split on the commas
     * of that level — never on commas nested inside arguments or arrays.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function commaSeparated(int $open, int $close): array
    {
        $ranges = [];
        $start = $open + 1;
        $depth = 0;

        for ($i = $open + 1; $i < $close; $i++) {
            $depth += $this->nestingDelta($this->tokens[$i]);
            if ($depth === 0 && $this->tokens[$i]->is(',')) {
                $ranges[] = [$start, $i - 1];
                $start = $i + 1;
            }
        }

        if ($start < $close) {
            $ranges[] = [$start, $close - 1];
        }

        return $ranges;
    }

    public function nestingDelta(\PhpToken $token): int
    {
        return match (true) {
            $token->is(self::OPENERS) => 1,
            $token->is(self::CLOSERS) => -1,
            default => 0,
        };
    }
}
