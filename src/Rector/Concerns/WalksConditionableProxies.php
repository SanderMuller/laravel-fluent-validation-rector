<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;

/**
 * Invariant checks for stepping through Conditionable hops (`when`, `unless`,
 * `whenInput`) in a receiver-type walk. Consumers decide what to do on
 * success or failure; this trait just answers "does this hop preserve the
 * receiver type".
 *
 * Why it lives in a trait: Laravel's Conditionable contract is shared across
 * `SimplifyRuleWrappersRector`, `PromoteFieldFactoryRector`, and future
 * rectors that need the same closure-body invariant. Extraction also keeps
 * the consuming rector's class cognitive complexity under threshold.
 *
 * @phpstan-require-extends AbstractRector
 */
trait WalksConditionableProxies
{
    /**
     * Invariant for Conditionable hops in a receiver walk. Returns true only
     * when every provided callback (`$callback` and `$default` on the 3-arg
     * `when(value, callback, default)` form) is a closure or arrow-fn whose
     * body returns `$this`-equivalent — either the closure's first parameter
     * directly, or a `MethodCall` chain rooted in that parameter. Any other
     * return shape (StaticCall, New_, a different Variable, Ternary, Match_,
     * function call) fails the check.
     *
     * Handles named args (`->when(value: $c, callback: $fn, default: $fn2)`)
     * via `Arg::$name` lookup. Single-callback form (no `$default`) only
     * checks `$callback`. Zero-callback form (`->when($c)` returning the
     * proxy itself) fails — dynamic dispatch.
     */
    private function conditionableHopPreservesReceiver(MethodCall $hop): bool
    {
        $callback = $this->extractConditionableCallback($hop, 1, 'callback');
        $default = $this->extractConditionableCallback($hop, 2, 'default');

        if (! $callback instanceof Closure && ! $callback instanceof ArrowFunction) {
            return false;
        }

        if (! $this->closureReturnsReceiver($callback)) {
            return false;
        }

        if ($default === null) {
            return true;
        }

        if (! $default instanceof Closure && ! $default instanceof ArrowFunction) {
            return false;
        }

        return $this->closureReturnsReceiver($default);
    }

    private function extractConditionableCallback(MethodCall $hop, int $positionalIndex, string $namedKey): ?Expr
    {
        foreach ($hop->args as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            if ($arg->name instanceof Identifier && $arg->name->toString() === $namedKey) {
                return $arg->value;
            }
        }

        if (isset($hop->args[$positionalIndex]) && $hop->args[$positionalIndex] instanceof Arg) {
            $positionalArg = $hop->args[$positionalIndex];

            if ($positionalArg->name instanceof Identifier && $positionalArg->name->toString() !== $namedKey) {
                return null;
            }

            return $positionalArg->value;
        }

        return null;
    }

    /**
     * Laravel `Conditionable::when/unless` uses `$callback($this, $value) ?? $this`,
     * so any callback that evaluates to a non-null value has that value
     * propagated as the chain receiver. The only closure shapes provably
     * safe to step through are therefore:
     *
     * 1. **No explicit return** (`function ($r) { $r->doThing(); }`): body-
     *    level side effects on `$r`, returns null, `when` substitutes `$this`.
     * 2. **Empty return** (`function ($r) { return; }`): same — `null`-coalesced.
     * 3. **Direct param return** (`fn ($r) => $r`): returns the receiver itself.
     *
     * Method-chain bodies like `fn ($r) => $r->nullable()` LOOK safe because
     * fluent builders conventionally return `$this`, but we cannot prove it
     * without reflecting each method's declared return type — and some methods
     * do NOT return `$this` (`getLabel()`, `compiledRules()`, macro/__call
     * dispatch). Codex review flagged the unchecked method-chain case as a
     * HIGH miscompile risk, so v1 conservatively rejects method-chain bodies.
     * Reflection-based return-type probing is a future hardening pass.
     */
    private function closureReturnsReceiver(Closure|ArrowFunction $closure): bool
    {
        if ($closure->params === []) {
            return false;
        }

        $firstParam = $closure->params[0];

        if (! $firstParam instanceof Param || ! $firstParam->var instanceof Variable) {
            return false;
        }

        $paramName = is_string($firstParam->var->name) ? $firstParam->var->name : null;

        if ($paramName === null) {
            return false;
        }

        return $this->closureBodyIsReceiverPreserving($closure, $paramName);
    }

    private function closureBodyIsReceiverPreserving(Closure|ArrowFunction $closure, string $paramName): bool
    {
        if ($closure instanceof ArrowFunction) {
            // Arrow-fn body must be exactly the param variable. No method
            // chain. (Arrow-fns always return their expression; there's no
            // "no return" form.)
            return $this->isBareParamVariable($closure->expr, $paramName);
        }

        // Standard Closure: walk `stmts`. Safe if (a) no Return_ at all, or
        // (b) every Return_ is either empty (`return;`) or returns the bare
        // param variable. Any other Return_ form fails.
        foreach ($closure->stmts as $stmt) {
            if (! $stmt instanceof Return_) {
                continue;
            }

            if (! $stmt->expr instanceof Expr) {
                continue;
            }

            if (! $this->isBareParamVariable($stmt->expr, $paramName)) {
                return false;
            }
        }

        return true;
    }

    private function isBareParamVariable(?Expr $expr, string $paramName): bool
    {
        return $expr instanceof Variable
            && is_string($expr->name)
            && $expr->name === $paramName;
    }
}
