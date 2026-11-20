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
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidationRector\Internal\DescendantMethodCalls;

/**
 * Finds rules arrays whose keys combine with other arrays at runtime. A
 * wildcard fold turns 'items.*' into a parent key 'items'. When the array
 * combines with another one (a spread, array_merge() or `+`), that parent
 * key and a rule with the same key replace each other. The arrays can
 * combine in the same class, or in a subclass that calls the method.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait GuardsFoldKeyCollisions
{
    /**
     * The reason to skip folding in `$method`, or null when a fold is safe.
     *
     * @param  array<string, true>  $calledOwnMethodNames  from collectCalledOwnMethodNames()
     */
    private function foldCollisionReason(Class_ $class, ClassMethod $method, array $calledOwnMethodNames): ?string
    {
        $methodName = (string) $this->getName($method);

        if (isset($calledOwnMethodNames[strtolower($methodName)])) {
            return sprintf('%s() result is used by another method of the class — a folded parent key could replace a rule of the same key there', $methodName);
        }

        // A subclass cannot call a private method, and a final or anonymous
        // class has no subclass.
        if ($method->isPrivate() || $class->isFinal() || ! $class->name instanceof Identifier) {
            return null;
        }

        $className = $this->getName($class);
        $projectRoot = DescendantMethodCalls::findProjectRoot($this->getFile()->getFilePath());

        if ($className === null || $projectRoot === null
            || ! DescendantMethodCalls::isCalledByDescendant($className, $methodName, $projectRoot)) {
            return null;
        }

        return sprintf('%s() is called by a subclass — a folded parent key could replace a rule of the same key there', $methodName);
    }

    /**
     * Records each class in the namespace that extends another class, with
     * the methods it calls, so a parent in the same file sees its subclass
     * before the project scan runs.
     */
    private function recordDescendantCalls(Namespace_ $namespace): void
    {
        foreach ($namespace->stmts as $stmt) {
            if (! $stmt instanceof Class_ || ! $stmt->extends instanceof Name) {
                continue;
            }

            $className = $this->getName($stmt);
            $parentName = $this->getName($stmt->extends);

            if ($className === null || $parentName === null) {
                continue;
            }

            $names = array_keys($this->collectCalledOwnMethodNames($stmt, includeParentCalls: true));
            DescendantMethodCalls::record($className, $parentName, array_map(strval(...), $names));
        }
    }

    /**
     * Lower-cased names of the methods the class calls on itself through
     * `$this->name()`, `self::name()` or `static::name()`, and also
     * `parent::name()` when `$includeParentCalls` is true.
     *
     * @return array<string, true>
     */
    private function collectCalledOwnMethodNames(Class_ $class, bool $includeParentCalls = false): array
    {
        $receivers = $includeParentCalls ? ['self', 'static', 'parent'] : ['self', 'static'];
        $names = [];

        $this->traverseNodesWithCallable($class->stmts, static function (Node $node) use (&$names, $receivers): null {
            if ($node instanceof MethodCall
                && $node->var instanceof Variable
                && $node->var->name === 'this'
                && $node->name instanceof Identifier) {
                $names[$node->name->toLowerString()] = true;
            }

            if ($node instanceof StaticCall
                && $node->class instanceof Name
                && in_array($node->class->toLowerString(), $receivers, true)
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
