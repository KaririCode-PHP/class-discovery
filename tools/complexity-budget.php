<?php

declare(strict_types=1);

/**
 * Spec V4.x §13 complexity budgets not covered by the PHPStan cognitive-complexity
 * extension: cyclomatic ≤ 10 (McCabe), nesting ≤ 3, parameters ≤ 4 per method,
 * public methods ≤ 10 per class (soft — reported, never fails the gate).
 *
 * DEC-002 (accepted, ADR-009): the constructor of a `readonly` class
 * whose parameters are all promoted *is* the data shape of a DPO/Value Object —
 * called with named arguments (§28), it cannot be shortened without a Parameter
 * Object that only relocates the same fields. Such constructors are reported as
 * exemptions, not violations. Set EXEMPT_READONLY_PROMOTED_CONSTRUCTORS to false
 * to enforce the budget on them.
 *
 * Usage: php tools/complexity-budget.php src
 */

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

const MAX_CYCLOMATIC = 10;
const MAX_NESTING = 3;
const MAX_PARAMETERS = 4;
const MAX_PUBLIC_METHODS = 10;
const EXEMPT_READONLY_PROMOTED_CONSTRUCTORS = true;

/** Decision points per McCabe: each adds one independent path. */
function cyclomatic(Node $function): int
{
    $decisions = new NodeFinder()->find($function->getStmts() ?? [], static fn (Node $n): bool => match (true) {
        $n instanceof Node\Stmt\If_, $n instanceof Node\Stmt\ElseIf_, $n instanceof Node\Stmt\For_,
        $n instanceof Node\Stmt\Foreach_, $n instanceof Node\Stmt\While_, $n instanceof Node\Stmt\Do_,
        $n instanceof Node\Stmt\Catch_, $n instanceof Node\Expr\Ternary,
        $n instanceof Node\Expr\BinaryOp\BooleanAnd, $n instanceof Node\Expr\BinaryOp\BooleanOr,
        $n instanceof Node\Expr\BinaryOp\LogicalAnd, $n instanceof Node\Expr\BinaryOp\LogicalOr,
        $n instanceof Node\Expr\BinaryOp\Coalesce => true,
        $n instanceof Node\Stmt\Case_ => $n->cond !== null,
        $n instanceof Node\MatchArm => $n->conds !== null,
        default => false,
    });

    return 1 + count($decisions);
}

/** Deepest nesting of control structures inside the function body. */
function nesting(array $stmts, int $depth = 0): int
{
    $max = $depth;
    foreach ($stmts as $stmt) {
        $isBlock = $stmt instanceof Node\Stmt\If_ || $stmt instanceof Node\Stmt\For_
            || $stmt instanceof Node\Stmt\Foreach_ || $stmt instanceof Node\Stmt\While_
            || $stmt instanceof Node\Stmt\Do_ || $stmt instanceof Node\Stmt\Switch_
            || $stmt instanceof Node\Stmt\TryCatch;
        $children = [];
        foreach ($stmt->getSubNodeNames() as $name) {
            $sub = $stmt->{$name};
            foreach (is_array($sub) ? $sub : [$sub] as $child) {
                if ($child instanceof Node\Stmt || $child instanceof Node\Stmt\ElseIf_ || $child instanceof Node\Stmt\Else_
                    || $child instanceof Node\Stmt\Case_ || $child instanceof Node\Stmt\Catch_ || $child instanceof Node\Stmt\Finally_) {
                    $children[] = $child;
                }
            }
        }
        $max = max($max, nesting($children, $depth + ($isBlock ? 1 : 0)));
    }

    return $max;
}

$parser = new ParserFactory()->createForNewestSupportedVersion();
$violations = [];
$notes = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[1] ?? 'src', FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $ast = $parser->parse((string) file_get_contents($file->getPathname())) ?? [];
    foreach (new NodeFinder()->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $class) {
        $public = 0;
        foreach ($class->getMethods() as $method) {
            $public += $method->isPublic() ? 1 : 0;
            $id = ($class->name?->toString() ?? 'anonymous') . '::' . $method->name->toString() . '()';
            $metrics = ['cyclomatic' => [cyclomatic($method), MAX_CYCLOMATIC],
                'nesting' => [nesting($method->stmts ?? []), MAX_NESTING],
                'parameters' => [count($method->params), MAX_PARAMETERS]];
            $exempt = EXEMPT_READONLY_PROMOTED_CONSTRUCTORS
                && $method->name->toLowerString() === '__construct'
                && $class instanceof Node\Stmt\Class_ && $class->isReadonly()
                && array_all($method->params, static fn (Node\Param $p): bool => $p->isPromoted());
            foreach ($metrics as $metric => [$value, $limit]) {
                if ($value <= $limit) {
                    continue;
                }
                $line = sprintf('%-60s %s %d > %d', $id, $metric, $value, $limit);
                if ($exempt && $metric === 'parameters') {
                    $notes[] = $line . ' (DEC-002 exemption: readonly promoted constructor)';
                } else {
                    $violations[] = $line;
                }
            }
        }
        if ($public > MAX_PUBLIC_METHODS) {
            $notes[] = sprintf('%-60s public methods %d > %d (soft)', $class->name?->toString(), $public, MAX_PUBLIC_METHODS);
        }
    }
}

array_map(static fn (string $line) => print("  VIOLATION  {$line}\n"), $violations);
array_map(static fn (string $line) => print("  note       {$line}\n"), $notes);
echo $violations === [] ? "Complexity budgets (§13): OK\n" : sprintf("Complexity budgets (§13): %d violation(s)\n", count($violations));
exit($violations === [] ? 0 : 1);
