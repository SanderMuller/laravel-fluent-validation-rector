<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitor;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;

/**
 * Detection and receiver-rewriting of the rule-building calls inside a rules()
 * method that `ConvertToFluentSchemaRector` renames to schema(FluentSchema).
 * Covers the `FluentRule::` static factory and the self-referential
 * `parent::rules()` / `self::rules()` / `static::rules()` / `$this->rules()`
 * calls that would otherwise dangle once rules() is renamed.
 *
 * Extracted from the rector to keep its class-level cognitive complexity in
 * check; the reflection-driven "does the parent convert?" decision stays on
 * the rector (it leans on {@see DetectsInheritedTraits}).
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait RewritesRuleMethodCalls
{
    /**
     * Rewrite the rule-building calls in the method body onto the injected
     * `$builderName` FluentSchema parameter:
     * `FluentRule::x(...)` → `$builder->x(...)`, and a self/parent `rules()`
     * call → the matching `schema($builder)` call.
     */
    private function rewriteRuleCallsForSchema(ClassMethod $method, string $builderName): void
    {
        $this->traverseNodesWithCallable($method->stmts ?? [], function (Node $subNode) use ($builderName): ?Node {
            // Thread the injected builder into any explicit closure whose body —
            // or a nested closure/arrow-fn within it — builds a rule with
            // `FluentRule::` or a parent/self `rules()` call. Those receivers are
            // rewritten to `$<builder>` below, so the closure has to capture it
            // via `use`. Arrow functions auto-capture (no `use` needed); anonymous
            // classes and nested named functions are rejected upstream by
            // methodBodyIsSafeToInjectBuilder(). `resolveBuilderParamName()` has
            // already picked a builder name distinct from any closure parameter,
            // so this capture never shadows the closure's own arguments. Mutate in
            // place and return null so traversal still descends to rewrite the
            // calls (and to capture any nested closures on the way down).
            if ($subNode instanceof Closure
                && $this->containsUncapturedRuleCall($subNode)
                && ! $this->closureCaptures($subNode, $builderName)) {
                $subNode->uses[] = new ClosureUse(new Variable($builderName));

                return null;
            }

            // `FluentRule::string(...)` → `$<builder>->string(...)`. FluentSchema
            // mirrors every FluentRule factory 1:1 (and forwards macros via
            // __call), so the receiver swap preserves the produced rule.
            if ($subNode instanceof StaticCall && $this->isFluentRuleStaticCall($subNode) && $subNode->name instanceof Identifier) {
                return new MethodCall(new Variable($builderName), $subNode->name, $subNode->args);
            }

            // `parent::rules()` / `self::rules()` / `static::rules()` →
            // `parent::schema($builder)` etc. The target rules() is being
            // renamed to schema(FluentSchema); forward the builder so the
            // (renamed) parent/self method resolves and returns its rule set.
            // Guarded upstream: parent calls only reach here when the parent
            // provably converts (or #[FluentRules] opts in), and self calls
            // only when they live in the converted body.
            if ($subNode instanceof StaticCall
                && $subNode->class instanceof Name
                && $subNode->name instanceof Identifier
                && strcasecmp($subNode->name->toString(), 'rules') === 0
                && in_array(strtolower($subNode->class->toString()), ['parent', 'self', 'static'], true)) {
                return new StaticCall($subNode->class, new Identifier('schema'), [new Arg(new Variable($builderName))]);
            }

            // `$this->rules()` → `$this->schema($builder)`.
            if ($subNode instanceof MethodCall
                && $subNode->var instanceof Variable
                && $subNode->var->name === 'this'
                && $subNode->name instanceof Identifier
                && strcasecmp($subNode->name->toString(), 'rules') === 0) {
                return new MethodCall($subNode->var, new Identifier('schema'), [new Arg(new Variable($builderName))]);
            }

            return null;
        });
    }

    /**
     * Whether the subtree contains a rule call whose receiver this rule would
     * rewrite to the injected builder — a FluentRule factory static, or a
     * parent/self `rules()` call. Used to reject non-capturing nested scopes
     * (plain closures, anonymous classes, nested functions) where the builder
     * would be out of scope.
     */
    private function containsUncapturedRuleCall(Node $node): bool
    {
        $found = false;

        $this->traverseNodesWithCallable($node, function (Node $subNode) use (&$found): ?int {
            if (($subNode instanceof StaticCall && $this->isFluentRuleStaticCall($subNode))
                || $this->isSelfRulesCall($subNode)
                || $this->isParentRulesCall($subNode)) {
                $found = true;

                return NodeVisitor::STOP_TRAVERSAL;
            }

            return null;
        });

        return $found;
    }

    /**
     * Whether `$closure` already lists `$name` in its `use (...)` capture — so
     * the builder isn't added twice (idempotent, and respectful of a hand-written
     * capture of the same name).
     */
    private function closureCaptures(Closure $closure, string $name): bool
    {
        foreach ($closure->uses as $use) {
            if ($use->var instanceof Variable && $use->var->name === $name) {
                return true;
            }
        }

        return false;
    }

    private function isFluentRuleStaticCall(StaticCall $call): bool
    {
        $className = $this->getName($call->class);

        return $className === FluentRule::class || $className === 'FluentRule';
    }

    /**
     * A `$this->rules()`, `self::rules()`, or `static::rules()` call — a
     * reference to the same class's rules() method (case-insensitive, as PHP
     * method names are). `parent::rules()` is deliberately excluded (see
     * {@see isParentRulesCall()}); it targets a different class.
     */
    private function isSelfRulesCall(Node $node): bool
    {
        if ($node instanceof MethodCall) {
            return $node->var instanceof Variable
                && $node->var->name === 'this'
                && $node->name instanceof Identifier
                && strcasecmp($node->name->toString(), 'rules') === 0;
        }

        if ($node instanceof StaticCall) {
            return $node->class instanceof Name
                && in_array(strtolower($node->class->toString()), ['self', 'static'], true)
                && $node->name instanceof Identifier
                && strcasecmp($node->name->toString(), 'rules') === 0;
        }

        return false;
    }

    private function isParentRulesCall(Node $node): bool
    {
        return $node instanceof StaticCall
            && $node->class instanceof Name
            && strcasecmp($node->class->toString(), 'parent') === 0
            && $node->name instanceof Identifier
            && strcasecmp($node->name->toString(), 'rules') === 0;
    }

    /**
     * Whether any method OTHER than the one being converted calls the class's
     * own rules() ($this->/self::/static::). Such a call would dangle after the
     * rename and can't be rewritten (no builder in scope there).
     */
    private function hasSelfRulesCallOutsideMethod(Class_ $class, ClassMethod $converted): bool
    {
        foreach ($class->getMethods() as $classMethod) {
            if ($classMethod === $converted) {
                continue;
            }

            $found = false;

            $this->traverseNodesWithCallable($classMethod->stmts ?? [], function (Node $subNode) use (&$found): ?int {
                if ($this->isSelfRulesCall($subNode)) {
                    $found = true;

                    return NodeVisitor::STOP_TRAVERSAL;
                }

                return null;
            });

            if ($found) {
                return true;
            }
        }

        return false;
    }

    private function bodyCallsParentRules(ClassMethod $method): bool
    {
        $found = false;

        $this->traverseNodesWithCallable($method->stmts ?? [], function (Node $subNode) use (&$found): ?int {
            if ($this->isParentRulesCall($subNode)) {
                $found = true;

                return NodeVisitor::STOP_TRAVERSAL;
            }

            return null;
        });

        return $found;
    }
}
