<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use SanderMuller\FluentValidation\RuleSet;

/**
 * Shared descent helpers for rectors that fold or convert array
 * payloads inside `RuleSet::from([...])` static-call wrappers.
 *
 * The wrapper is the canonical sister-package idiom for inline
 * validation (`RuleSet::from([...])->check($data)->safe()`) and for
 * FormRequest `rules(): RuleSet` returns. Rectors that visit array-
 * shaped rule payloads must descend into the wrapper to avoid
 * silent-partial-application: the consumer's request ("convert these
 * rules" / "fold this wildcard") doesn't land if the wrapper hides
 * the inner Array_ from the rector's anchor predicate.
 *
 * Originally written inline in `GroupWildcardRulesToEachRector`
 * (0.19.1) and extracted into this shared trait in 1.1.0 when
 * `ValidationStringToFluentRuleRector` and
 * `ValidationArrayToFluentRuleRector` gained their own descent
 * paths. Single source of truth for the wrapper-recognition
 * semantics.
 *
 * Companion fixture / regression pin: see
 * `tests/GroupWildcardRulesToEach/Fixture/skip_ruleset_from_non_literal_argument.php.inc`
 * and the per-rector skip-fixture pairs added in 1.1.0.
 *
 * @internal
 */
trait DescendsIntoRuleSetFromWrapper
{
    /**
     * Returns true when `$expr` is a `RuleSet::from(...)` static call,
     * ignoring argument shape. Matches both FQN and short-name `RuleSet`
     * references (consumers may import or use the FQCN inline).
     *
     * Caller pairs this with `extractArrayFromRuleSetFrom()` to
     * differentiate "not a RuleSet::from() call at all" (silent — out
     * of scope for the rector) from "RuleSet::from() with a non-array
     * argument" (log — actionable; consumer's static-determinability
     * doesn't permit descent).
     */
    private function isRuleSetFromCall(Expr $expr): bool
    {
        if (! $expr instanceof StaticCall) {
            return false;
        }

        $className = $this->getName($expr->class);

        if ($className !== RuleSet::class && $className !== 'RuleSet') {
            return false;
        }

        return $expr->name instanceof Identifier && $expr->name->name === 'from';
    }

    /**
     * If `$expr` is `RuleSet::from(<Array_>)` with a single literal-Array_
     * argument, return the wrapped `Array_`; else null.
     *
     * Returns null for `RuleSet::from($injected)`,
     * `RuleSet::from(self::base())`, multi-arg, etc. Pair with
     * `isRuleSetFromCall()` to differentiate "log when descent target
     * is non-literal" vs "silently skip when it isn't a from() call at
     * all."
     */
    private function extractArrayFromRuleSetFrom(Expr $expr): ?Array_
    {
        if (! $this->isRuleSetFromCall($expr)) {
            return null;
        }

        /** @var StaticCall $expr — narrowed by isRuleSetFromCall */
        if (count($expr->args) !== 1) {
            return null;
        }

        $arg = $expr->args[0];

        if (! $arg instanceof Arg || ! $arg->value instanceof Array_) {
            return null;
        }

        return $arg->value;
    }
}
