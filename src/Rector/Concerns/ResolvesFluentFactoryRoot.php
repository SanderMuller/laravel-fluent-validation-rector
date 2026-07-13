<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentSchema;

/**
 * Recognizes the root factory call of a fluent-validation chain in either
 * spelling: the static `FluentRule::string()` form, or the instance-based
 * `$rules->string()` form produced by the FluentSchema builder (1.31+).
 *
 * The SIMPLIFY / POLISH rectors walk a chain tail inward (`$call->var` …) to
 * find the factory that seeds the receiver type. A FluentRule root is a
 * `StaticCall`; a FluentSchema root is a `MethodCall` whose receiver is a
 * `FluentSchema`-typed variable — the injected `schema(FluentSchema $rules)`
 * parameter, or a `RuleSet::define(fn (FluentSchema $rules) => …)` closure
 * parameter. Both spellings map the SAME factory-method name to the SAME typed
 * rule class (FluentSchema mirrors FluentRule 1:1), so callers keep their
 * existing name→class tables and only swap the root check.
 *
 * Chain walkers stop at `isFluentSchemaFactoryCall()` before consuming the
 * seed (a FluentSchema seed is a `MethodCall`, so a naive `while ($current
 * instanceof MethodCall)` loop would otherwise swallow it as a hop), then
 * accept the terminal via `isFluentFactoryRoot()` and read the factory name
 * via `fluentFactoryName()`.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait ResolvesFluentFactoryRoot
{
    /**
     * True when `$call` is the instance-form factory seed `$rules->string()`
     * — a method call whose receiver resolves to `FluentSchema`. Only the seed
     * has the builder as its receiver; every later hop in the chain has a rule
     * object as its receiver, so this uniquely marks the root.
     *
     * The `Variable` receiver check gates the type resolution: only a
     * bare-variable receiver (`$rules`) can be the builder, so chain hops
     * (whose receiver is another `MethodCall` / `StaticCall`) skip the
     * `isObjectType()` cost entirely.
     */
    private function isFluentSchemaFactoryCall(MethodCall $call): bool
    {
        return $this->fluentSchemaFactoryName($call) !== null;
    }

    /**
     * The factory name (`string`, `email`, …) when `$call` is a FluentSchema
     * seed, else null. Resolves the receiver type ONCE — chain walkers read
     * the name here at the break point, so a `isFluentSchemaFactoryCall()`
     * followed by a separate `fluentFactoryName()` doesn't run the (hot-path)
     * `isObjectType()` twice on the same seed.
     */
    private function fluentSchemaFactoryName(MethodCall $call): ?string
    {
        if (! $call->name instanceof Identifier || ! $call->var instanceof Variable) {
            return null;
        }

        return $this->isObjectType($call->var, new ObjectType(FluentSchema::class))
            ? $call->name->toString()
            : null;
    }

    /**
     * True when `$node` is the static-form factory seed `FluentRule::string()`.
     * Accepts the short `FluentRule` name too, matching the widening the
     * chain-value predicates already use for un-imported fold output.
     */
    private function isFluentRuleStaticFactory(Node $node): bool
    {
        if (! $node instanceof StaticCall || ! $node->class instanceof Name || ! $node->name instanceof Identifier) {
            return false;
        }

        $className = $this->getName($node->class);

        return $className === FluentRule::class || $className === 'FluentRule';
    }

    /**
     * The factory name when `$node` is the static seed `FluentRule::string()`,
     * else null.
     */
    private function fluentRuleStaticFactoryName(Node $node): ?string
    {
        return $this->isFluentRuleStaticFactory($node) && $node instanceof StaticCall && $node->name instanceof Identifier
            ? $node->name->toString()
            : null;
    }

    /**
     * True when `$node` is a fluent chain's factory seed in either spelling.
     * Equivalent to `fluentFactoryName($node) !== null` — call one, not both,
     * to avoid resolving the receiver type twice.
     */
    private function isFluentFactoryRoot(Node $node): bool
    {
        return $this->fluentFactoryName($node) !== null;
    }

    /**
     * The factory-method name (`string`, `email`, `field`, …) from a root in
     * either spelling, or null when `$node` is not a factory root.
     */
    private function fluentFactoryName(Node $node): ?string
    {
        return $node instanceof MethodCall
            ? $this->fluentSchemaFactoryName($node)
            : $this->fluentRuleStaticFactoryName($node);
    }

    /**
     * True when `$method` is the FluentSchema builder hook: a method named
     * `schema` whose first parameter is type-hinted `FluentSchema`. The
     * parameter type — not the name alone — is the discriminator, mirroring
     * how `HasFluentRules::createDefaultValidator()` detects the hook at
     * runtime, so an unrelated `schema()` (e.g. one returning a JSON/DB
     * schema) is never mistaken for the builder.
     *
     * Method-discovery rules add this to their name gate so a `schema()`
     * body is processed alongside `rules()`; the per-chain SIMPLIFY rules
     * don't need it (they fire on the chain node, not the method).
     */
    private function isFluentSchemaMethod(ClassMethod $method): bool
    {
        // PHP method names are case-insensitive; compare accordingly.
        if (strcasecmp($this->getName($method) ?? '', 'schema') !== 0) {
            return false;
        }

        $firstParam = $method->params[0] ?? null;

        return $firstParam instanceof Param
            && $firstParam->type instanceof Name
            && $this->getName($firstParam->type) === FluentSchema::class;
    }
}
