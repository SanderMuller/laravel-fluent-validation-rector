<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use Illuminate\Validation\Rule;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use ReflectionMethod;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\ArrayRule;

/**
 * Treats a `FluentRule::field()` parent that carries a bare array rule
 * (`->rule(Rule::array())` or `->rule('array')`) as an `array()` parent for
 * wildcard grouping. The array factory seeds the same implicit `array` rule,
 * so the two are equivalent — but only `array()` exposes `each()`, so the
 * chain must be re-rooted before the wildcard fold can append it.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait PromotesArrayRuleParents
{
    /**
     * Hops that survive a `field()` → `array()` reroot. Restricted to
     * presence/dependency modifiers that are native to BOTH `FieldRule` and
     * `ArrayRule` (the shared `HasFieldModifiers` surface), so promotion is a
     * native→native swap with identical semantics.
     *
     * Excluded by design:
     *  - `rule()` escape hatches — object rules reorder relative to the seeded
     *    `array` constraint;
     *  - `when()`/`unless()`/`whenInput()` — closures may call `FieldRule`-only
     *    APIs on the array receiver;
     *  - `message()` — binds to the most-recent rule, which the stripped array
     *    rule may have been;
     *  - conditional `*If` / `*Unless` modifiers — their closure/bool form adds
     *    an object rule;
     *  - size constraints (`min`/`max`/`between`/`exactly`/`distinct`) — these
     *    are not native to `FieldRule`, so a `field()->min(…)` call can only be
     *    a consumer macro; promoting it would silently swap in ArrayRule's
     *    built-in.
     *
     * @var list<string>
     */
    private const array SAFE_PROMOTED_HOPS = [
        'nullable', 'required', 'sometimes', 'filled', 'present', 'prohibited',
        'requiredWith', 'requiredWithAll', 'requiredWithout', 'requiredWithoutAll',
        'presentWith', 'presentWithAll', 'missingWith', 'missingWithAll', 'prohibits',
        'bail',
    ];

    /**
     * Cache of `ArrayRule`'s public instance method names — a runtime backstop
     * so an allowlisted hop missing from the installed `ArrayRule` can never
     * be replayed into an invalid call.
     *
     * @var array<string, true>|null
     */
    private static ?array $arrayRuleMethods = null;

    /**
     * Resolve the parent factory for fold-eligibility, reporting a promotable
     * `field()` parent that carries a bare array rule as `array`.
     */
    private function arrayCompatibleParentFactory(Expr $parentValue): ?string
    {
        return $this->isPromotableFieldArrayParent($parentValue)
            ? 'array'
            : $this->fluentRuleRootFactory($parentValue);
    }

    /**
     * Rewrite a promotable `field()->…->rule(Rule::array())` parent into
     * `array()->…` with the redundant rule hop stripped. Non-promotable
     * parents are returned unchanged.
     */
    private function normalizeArrayRuleParent(Expr $parentValue): Expr
    {
        return $this->isPromotableFieldArrayParent($parentValue)
            ? $this->promoteFieldParentToArray($parentValue)
            : $parentValue;
    }

    /**
     * Whether a `field()` parent can be safely re-rooted to `array()`. All of:
     *  - a `FluentRule::field()` root with no arg — `array()`'s first parameter
     *    is `$keys`, not `$label`, so a `field('Label')` label can't be copied
     *    across positionally;
     *  - a bare array rule hop (`->rule(Rule::array())` / `->rule('array')`);
     *  - every other hop being in `SAFE_PROMOTED_HOPS` and present on
     *    `ArrayRule`.
     *
     * Any miss leaves the chain on its escape hatch (the wildcard fold skips).
     */
    private function isPromotableFieldArrayParent(Expr $parentValue): bool
    {
        [$root, $hops] = $this->splitFluentChain($parentValue);

        if (! $root instanceof StaticCall || $root->args !== []) {
            return false;
        }

        if ($this->fluentRuleRootFactory($parentValue) !== 'field') {
            return false;
        }

        $hasArrayRule = false;

        foreach ($hops as $hop) {
            if ($this->isArrayRuleHop($hop)) {
                $hasArrayRule = true;

                continue;
            }

            if (! $hop->name instanceof Identifier) {
                return false;
            }

            $name = $hop->name->toString();

            if (! in_array($name, self::SAFE_PROMOTED_HOPS, true) || ! $this->arrayRuleHasMethod($name)) {
                return false;
            }
        }

        return $hasArrayRule;
    }

    /**
     * Re-root a promotable `field()` chain as `array()`, dropping the bare
     * array rule hop: `field()->nullable()->rule(Rule::array())` →
     * `array()->nullable()`. Assumes `isPromotableFieldArrayParent()`.
     */
    private function promoteFieldParentToArray(Expr $parentValue): Expr
    {
        [$root, $hops] = $this->splitFluentChain($parentValue);

        $class = $root instanceof StaticCall ? $root->class : new Name('FluentRule');
        $rebuilt = new StaticCall($class, new Identifier('array'));

        foreach (array_reverse($hops) as $hop) {
            if ($this->isArrayRuleHop($hop)) {
                continue;
            }

            $rebuilt = new MethodCall($rebuilt, $hop->name, $hop->args);
        }

        return $rebuilt;
    }

    /**
     * Split a chain into its root expression and the method-call hops above
     * it (ordered outermost-first).
     *
     * @return array{0: Expr, 1: list<MethodCall>}
     */
    private function splitFluentChain(Expr $expr): array
    {
        $hops = [];
        $current = $expr;

        while ($current instanceof MethodCall) {
            $hops[] = $current;
            $current = $current->var;
        }

        return [$current, $hops];
    }

    /**
     * Whether `ArrayRule` exposes a public instance method of the given name.
     */
    private function arrayRuleHasMethod(string $name): bool
    {
        if (self::$arrayRuleMethods === null) {
            $methods = [];

            foreach ((new ReflectionClass(ArrayRule::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $methods[$method->getName()] = true;
            }

            self::$arrayRuleMethods = $methods;
        }

        return isset(self::$arrayRuleMethods[$name]);
    }

    /**
     * The factory method name at the root of a FluentRule chain
     * (`'array'`, `'field'`, …), or null if the chain isn't FluentRule-rooted.
     *
     * Matches both the fully-qualified class name and the short `FluentRule`
     * name: sibling converter rectors emit the short form when running in the
     * same set-list pass (their `use` import is queued via the post-rector
     * pipeline and isn't yet in the tree), and the short form is authoritative
     * once that pipeline finishes — so either name is safe to match.
     */
    private function fluentRuleRootFactory(Expr $expr): ?string
    {
        $current = $expr;

        while ($current instanceof MethodCall) {
            $current = $current->var;
        }

        if (! $current instanceof StaticCall || ! $current->name instanceof Identifier) {
            return null;
        }

        $className = $this->getName($current->class);

        if ($className !== FluentRule::class && $className !== 'FluentRule') {
            return null;
        }

        return $current->name->toString();
    }

    /**
     * Whether a chain hop is `->rule(<bare array rule>)`.
     */
    private function isArrayRuleHop(MethodCall $hop): bool
    {
        if (! $hop->name instanceof Identifier || $hop->name->toString() !== 'rule') {
            return false;
        }

        if (count($hop->args) !== 1 || ! $hop->args[0] instanceof Arg) {
            return false;
        }

        return $this->isArrayRulePayload($hop->args[0]->value);
    }

    /**
     * Whether a `->rule(...)` payload is the bare array rule — `Rule::array()`
     * with no arguments or the `'array'` string token. A keyed
     * `Rule::array(['a', 'b'])` is excluded: it carries `array:a,b` semantics
     * the bare `array()` factory does not seed.
     */
    private function isArrayRulePayload(Expr $payload): bool
    {
        if ($payload instanceof StaticCall
            && $payload->name instanceof Identifier
            && $payload->name->toString() === 'array'
            && $payload->args === []
            && $this->isObjectType($payload->class, new ObjectType(Rule::class))) {
            return true;
        }

        return $payload instanceof String_ && $payload->value === 'array';
    }
}
