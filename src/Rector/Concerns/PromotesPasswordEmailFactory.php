<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\Password;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use ReflectionMethod;
use SanderMuller\FluentValidation\Rules\EmailRule;
use SanderMuller\FluentValidation\Rules\PasswordRule;
use WeakMap;

/**
 * Trigger B logic for `PromoteFieldFactoryRector`:
 * `FluentRule::string()->…->rule(Password::default())` → `FluentRule::password()`
 * (and `Password::min($literal)`, `Email::default()` analogs).
 *
 * Extracted from the rector to keep the main class's cognitive complexity
 * within threshold. Runtime semantics and safety gates are unchanged — see
 * `specs/password-email-factory-promotion.md` for the design.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait PromotesPasswordEmailFactory
{
    /**
     * Chain modifier names that EXIST on the promotion target but with
     * divergent semantics or signatures. Presence in the chain forces a
     * bail even though the method-availability probe would otherwise pass.
     *
     * `StringRule::min(int, ?string)` / `StringRule::max(int, ?string)` add
     * a string-length validation rule. `PasswordRule::min(int)` /
     * `PasswordRule::max(int)` mutate the embedded Laravel Password
     * builder's min/max characters. Different semantics, different
     * signatures — promoting `string()->min(20)` to `password()->min(20)`
     * silently changes intent AND would TypeError at runtime on the
     * 2-arg `->min(20, 'msg')` form. Caught by Codex review 2026-04-24.
     *
     * `EmailRule::max(int, ?string)` shares signature + semantics with
     * `StringRule::max`, so it is not blocklisted. No `EmailRule::min`
     * exists, so string → email promotion with `->min()` bails earlier
     * via method-availability.
     *
     * Keep in sync with fluent-validation releases; a signature snapshot
     * test would be a nice future-guard.
     *
     * @var array<class-string, list<string>>
     */
    private const array DIVERGENT_MODIFIER_BLOCKLIST = [
        PasswordRule::class => ['min', 'max'],
    ];

    /**
     * Tracks chain roots whose Trigger B analysis has already been decided.
     * Rector's pre-order traversal fires the outermost `MethodCall` first,
     * which is the only position that can see the full chain; inner fires
     * arriving after must not re-analyze with a truncated view, or they
     * would e.g. splice one of two stacked `->rule(Password::*)` payloads
     * and miss the second — the double-rule bail would be defeated.
     *
     * @var WeakMap<StaticCall, true>|null
     */
    private ?WeakMap $passwordEmailTriggerVisited = null;

    /**
     * @param  list<string>  $conditionableHops
     */
    private function applyPasswordEmailTrigger(StaticCall $root, MethodCall $node, array $conditionableHops): ?Node
    {
        if (! $this->passwordEmailTriggerVisited instanceof WeakMap) {
            $this->passwordEmailTriggerVisited = new WeakMap();
        }

        if (isset($this->passwordEmailTriggerVisited[$root])) {
            return null;
        }

        $this->passwordEmailTriggerVisited[$root] = true;

        // Safety Gate #1: source factory must be zero-arg. FluentRule::string(?string $label)
        // → FluentRule::password(?int $min, ?string $label) rebinds the label to $min;
        // → FluentRule::email(?string $label, …) keeps binding but v1 ships zero-arg-only
        // for consistency. Structural arg-rebinding deferred.
        if ($root->args !== []) {
            return null;
        }

        $hops = $this->collectPasswordEmailHopsFromRoot($root, $node);

        if ($hops === []) {
            return null;
        }

        // Safety Gate #4: no Conditionable proxies. Closure-body receiver
        // analysis would be required to preserve type-narrowing across them.
        foreach ($hops as $hop) {
            if (! $hop->name instanceof Identifier) {
                return null;
            }

            if (in_array($hop->name->toString(), $conditionableHops, true)) {
                return null;
            }
        }

        $match = $this->findPasswordEmailMatchInHops($hops);

        if ($match === null) {
            return null;
        }

        // Safety Gate #2: every non-rule modifier must exist on the target
        // rule class. PasswordRule does NOT extend FieldRule; `same()` /
        // `different()` / `in()` / `regex()` etc. are declared per-rule and
        // absent from PasswordRule. Without this gate, promoting
        // `string()->same(...)` to `password()->same(...)` BadMethodCalls at
        // runtime (collectiq peer review, 2026-04-24).
        if (! $this->allModifiersAvailableOnTarget($hops, $match['index'], $match['promotion']['target_class'])) {
            return null;
        }

        return $this->spliceAndPromote($root, $node, $hops, $match['index'], $match['promotion']);
    }

    /**
     * @param  list<MethodCall>  $hops
     * @return array{index: int, promotion: array{factory: string, arg: ?Node\Expr, target_class: class-string}}|null
     */
    private function findPasswordEmailMatchInHops(array $hops): ?array
    {
        $matchedIndex = null;
        $promotion = null;

        foreach ($hops as $index => $hop) {
            if (! $hop->name instanceof Identifier) {
                return null;
            }

            if ($hop->name->toString() !== 'rule') {
                continue;
            }

            if (count($hop->args) !== 1 || ! $hop->args[0] instanceof Arg) {
                return null;
            }

            $resolved = $this->resolvePasswordEmailPayload($hop->args[0]->value);

            if ($resolved === null) {
                return null;
            }

            if ($matchedIndex !== null) {
                return null;
            }

            $matchedIndex = $index;
            $promotion = $resolved;
        }

        if ($matchedIndex === null || $promotion === null) {
            return null;
        }

        return ['index' => $matchedIndex, 'promotion' => $promotion];
    }

    /**
     * @param  list<MethodCall>  $hops
     * @param  class-string  $targetClass
     */
    private function allModifiersAvailableOnTarget(array $hops, int $matchedIndex, string $targetClass): bool
    {
        $blocklist = self::DIVERGENT_MODIFIER_BLOCKLIST[$targetClass] ?? [];

        foreach ($hops as $index => $hop) {
            if ($index === $matchedIndex) {
                continue;
            }

            if (! $hop->name instanceof Identifier) {
                return false;
            }

            $methodName = $hop->name->toString();

            // Bail on methods whose name matches on target but whose
            // semantics or signature diverge — e.g. `StringRule::min(int, ?msg)`
            // vs `PasswordRule::min(int)`. See DIVERGENT_MODIFIER_BLOCKLIST
            // docblock.
            if (in_array($methodName, $blocklist, true)) {
                return false;
            }

            if (! $this->methodExistsOnPasswordEmailTarget($targetClass, $methodName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<MethodCall>  $hops
     * @param  array{factory: string, arg: ?Node\Expr, target_class: class-string}  $promotion
     */
    private function spliceAndPromote(StaticCall $root, MethodCall $node, array $hops, int $matchedIndex, array $promotion): Node
    {
        $matchedHop = $hops[$matchedIndex];

        if ($matchedHop === $node) {
            $replacement = $matchedHop->var;
        } else {
            foreach ($hops as $hop) {
                if ($hop->var === $matchedHop) {
                    $hop->var = $matchedHop->var;

                    break;
                }
            }

            $replacement = $node;
        }

        $root->name = new Identifier($promotion['factory']);

        if ($promotion['arg'] !== null) {
            $root->args = [new Arg($promotion['arg'])];
        }

        return $replacement;
    }

    /**
     * @return array{factory: string, arg: ?Node\Expr, target_class: class-string}|null
     */
    private function resolvePasswordEmailPayload(Expr $value): ?array
    {
        if (! $value instanceof StaticCall) {
            return null;
        }

        if (! $value->class instanceof Name || ! $value->name instanceof Identifier) {
            return null;
        }

        $className = $this->getName($value->class);
        $methodName = $value->name->toString();

        if ($className === Password::class) {
            if ($methodName === 'default' && $value->args === []) {
                return ['factory' => 'password', 'arg' => null, 'target_class' => PasswordRule::class];
            }

            if ($methodName === 'min' && count($value->args) === 1 && $value->args[0] instanceof Arg) {
                $argExpr = $value->args[0]->value;

                if ($argExpr instanceof Int_ || $argExpr instanceof ClassConstFetch) {
                    return ['factory' => 'password', 'arg' => $argExpr, 'target_class' => PasswordRule::class];
                }
            }

            return null;
        }

        if ($className === Email::class && $methodName === 'default' && $value->args === []) {
            return ['factory' => 'email', 'arg' => null, 'target_class' => EmailRule::class];
        }

        return null;
    }

    /**
     * @return list<MethodCall>
     */
    private function collectPasswordEmailHopsFromRoot(StaticCall $root, MethodCall $currentCall): array
    {
        $hops = [];
        $current = $currentCall;

        while ($current instanceof MethodCall) {
            $hops[] = $current;
            $current = $current->var;
        }

        if ($current !== $root) {
            return [];
        }

        return array_reverse($hops);
    }

    /**
     * @param  class-string  $class
     */
    private function methodExistsOnPasswordEmailTarget(string $class, string $method): bool
    {
        /** @var array<class-string, array<string, true>> $cache */
        static $cache = [];

        if (! isset($cache[$class])) {
            $methods = [];

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $reflected) {
                if (! $reflected->isStatic()) {
                    $methods[$reflected->getName()] = true;
                }
            }

            $cache[$class] = $methods;
        }

        return isset($cache[$class][$method]);
    }
}
