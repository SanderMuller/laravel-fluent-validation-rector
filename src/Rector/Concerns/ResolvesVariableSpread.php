<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeVisitor;
use Rector\Rector\AbstractRector;

/**
 * Extracts the variable-spread resolver from
 * `InlineResolvableParentRulesRector`. Given a `...$base` spread in the
 * `rules()` return array, resolves `$base` back to a top-level assignment
 * whose RHS is either a literal `Array_` or `parent::rules()`, enforcing
 * the dominance-approximating gates from the spec.
 *
 * The using class must also provide `isParentRulesStaticCall()`,
 * `resolveParentRulesItems()`, and `cloneArrayItem()` — these are
 * shared with the `parent::rules()` resolver path and kept on the rector
 * so the two resolvers see the same definitions.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait ResolvesVariableSpread
{
    /**
     * @return list<ArrayItem>|null
     */
    private function resolveVariableSpread(Class_ $class, ClassMethod $method, Variable $spreadTarget): ?array
    {
        $varName = $this->getName($spreadTarget);

        if ($varName === null) {
            return null;
        }

        if ($this->methodContainsUnsetOf($method, $varName)) {
            return null;
        }

        if ($this->countVariableReferences($method, $varName) !== 2) {
            // Expected: 1 write (LHS of the assign) + 1 read (the spread).
            // Any other count means the variable is touched elsewhere and
            // a straight inline would drop or duplicate behavior.
            return null;
        }

        // Reject when the method body has ANY other top-level assignments
        // besides the `$base = ...` we want to inline. Codex review
        // (2026-04-24) caught that `stripDeadVariableSpreadAssign` could
        // reorder execution when siblings exist: `$a = sideA(); $base =
        // sideBase(); $b = sideB(); return [...$base];` transforms to
        // `$a = sideA(); $b = sideB(); return [<inlined items whose
        // RHS ran sideBase>];` — `sideBase()` now runs AFTER `sideB()`
        // instead of between `sideA` and `sideB`. Requiring a single
        // top-level assign (the spread source itself) eliminates any
        // reorderable peer.
        if ($this->countTopLevelAssignments($method) !== 1) {
            return null;
        }

        $assignment = $this->findSingleTopLevelAssignTo($method, $varName);

        if (! $assignment instanceof Assign) {
            return null;
        }

        $rhs = $assignment->expr;

        if ($rhs instanceof Array_) {
            $items = [];

            foreach ($rhs->items as $item) {
                if (! $item instanceof ArrayItem) {
                    return null;
                }

                // Nested spreads inside the variable's RHS are out of
                // scope — one hop only, same invariant as the parent
                // resolver.
                if ($item->unpack) {
                    return null;
                }

                $items[] = $this->cloneArrayItem($item);
            }

            return $items;
        }

        // Recursive hop: `$base = parent::rules();` — reuse the parent
        // resolver. Any other RHS shape (method call, ternary,
        // concatenation, etc.) is intentionally unresolvable and falls
        // back to leaving the spread intact.
        if ($this->isParentRulesStaticCall($rhs)) {
            return $this->resolveParentRulesItems($class);
        }

        return null;
    }

    /**
     * Return the single top-level `Assign` writing to `$varName` in the
     * method body. Returns null if the variable is written to zero or
     * multiple times at the top level, or if a top-level write targets
     * a non-`Variable` LHS (array destructuring, property assign, etc.).
     */
    private function findSingleTopLevelAssignTo(ClassMethod $method, string $varName): ?Assign
    {
        $found = null;

        foreach ($method->stmts ?? [] as $stmt) {
            if (! $stmt instanceof Expression) {
                continue;
            }

            if (! $stmt->expr instanceof Assign) {
                continue;
            }

            $lhs = $stmt->expr->var;

            if (! $lhs instanceof Variable) {
                continue;
            }

            if ($this->getName($lhs) !== $varName) {
                continue;
            }

            if ($found instanceof Assign) {
                return null;
            }

            $found = $stmt->expr;
        }

        return $found;
    }

    /**
     * Drop the `$var = ...;` top-level assignment stmt whose LHS matches
     * `$spreadTarget`. Called after a successful variable-spread inline,
     * where the variable is guaranteed single-use so the assignment is
     * now dead. Only removes the FIRST matching stmt (the gate already
     * guaranteed uniqueness).
     */
    private function stripDeadVariableSpreadAssign(ClassMethod $method, Variable $spreadTarget): void
    {
        $varName = $this->getName($spreadTarget);

        if ($varName === null || $method->stmts === null) {
            return;
        }

        foreach ($method->stmts as $index => $stmt) {
            if (! $stmt instanceof Expression) {
                continue;
            }

            if (! $stmt->expr instanceof Assign) {
                continue;
            }

            $lhs = $stmt->expr->var;

            if ($lhs instanceof Variable && $this->getName($lhs) === $varName) {
                unset($method->stmts[$index]);
                $method->stmts = array_values($method->stmts);

                return;
            }
        }
    }

    /**
     * Count top-level `Expression(Assign)` statements in the method body.
     * Used to gate variable-spread inlining: any value > 1 means there's
     * a peer assignment whose relative execution order could change
     * when we strip the spread source, so we bail.
     */
    private function countTopLevelAssignments(ClassMethod $method): int
    {
        $count = 0;

        foreach ($method->stmts ?? [] as $stmt) {
            if ($stmt instanceof Expression && $stmt->expr instanceof Assign) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Count how many times the named variable appears anywhere in the
     * method (including nested scopes — conservative by design). Used to
     * reject cases where `$base` is consumed by something OTHER than the
     * single spread at the top of the return array.
     */
    private function countVariableReferences(ClassMethod $method, string $varName): int
    {
        $count = 0;

        $this->traverseNodesWithCallable((array) $method->stmts, function (Node $node) use (&$count, $varName): null {
            if ($node instanceof Variable && $this->getName($node) === $varName) {
                ++$count;
            }

            return null;
        });

        return $count;
    }

    private function methodContainsUnsetOf(ClassMethod $method, string $varName): bool
    {
        $found = false;

        $this->traverseNodesWithCallable((array) $method->stmts, function (Node $node) use (&$found, $varName): ?int {
            if (! $node instanceof Unset_) {
                return null;
            }

            foreach ($node->vars as $var) {
                if ($var instanceof Variable && $this->getName($var) === $varName) {
                    $found = true;

                    return NodeVisitor::STOP_TRAVERSAL;
                }
            }

            return null;
        });

        return $found;
    }
}
