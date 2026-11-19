<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;

/**
 * Finds rules arrays whose keys combine with other arrays at runtime. A
 * wildcard fold turns 'items.*' into a parent key 'items'. When the array
 * combines with another one (a spread, array_merge() or `+`), that parent
 * key and a rule with the same key replace each other.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait GuardsFoldKeyCollisions
{
    /**
     * Lower-cased names of the methods the class calls on itself through
     * `$this->name()`, `self::name()` or `static::name()`.
     *
     * @return array<string, true>
     */
    private function collectCalledOwnMethodNames(Class_ $class): array
    {
        $names = [];

        $this->traverseNodesWithCallable($class->stmts, static function (Node $node) use (&$names): null {
            if ($node instanceof MethodCall
                && $node->var instanceof Variable
                && $node->var->name === 'this'
                && $node->name instanceof Identifier) {
                $names[$node->name->toLowerString()] = true;
            }

            if ($node instanceof StaticCall
                && $node->class instanceof Name
                && in_array($node->class->toLowerString(), ['self', 'static'], true)
                && $node->name instanceof Identifier) {
                $names[$node->name->toLowerString()] = true;
            }

            return null;
        });

        return $names;
    }

    private function hasSpreadItem(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem && $item->unpack) {
                return true;
            }
        }

        return false;
    }
}
