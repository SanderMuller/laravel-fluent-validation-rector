<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitor;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\RuleSet;

/**
 * Classifies `$this->validate(...)` / `$this->validateOnly(...)` calls on a
 * Livewire component and extracts the top-level string keys from the
 * explicit rule-array argument. Shared between `ConvertLivewireRuleAttributeRector`
 * (decides hybrid-bail vs partial-overlap skip) and any future rector that
 * needs the same safety gates.
 *
 * See spec `livewire-attribute-overlap-config.md` for the accepted-wrapper
 * list and the `unsafe` classification rules.
 *
 * @phpstan-require-extends AbstractRector
 */
trait ExtractsExplicitValidateKeys
{
    /**
     * @return list<string>|'unsafe'
     */
    private function extractExplicitValidateKeys(Class_ $class): array|string
    {
        $keys = [];
        $unsafe = false;

        $this->traverseNodesWithCallable($class, function (Node $inner) use (&$keys, &$unsafe): ?int {
            if ($unsafe) {
                return NodeVisitor::STOP_TRAVERSAL;
            }

            if (! $inner instanceof MethodCall) {
                return null;
            }

            $rulesArg = $this->extractRulesArgFromValidateCall($inner);

            if ($rulesArg === false) {
                return null;
            }

            if (! $rulesArg instanceof Arg) {
                return null;
            }

            $array = $this->extractArrayLiteralFromValidateArg($rulesArg->value);

            if (! $array instanceof Array_) {
                $unsafe = true;

                return NodeVisitor::STOP_TRAVERSAL;
            }

            foreach ($array->items as $item) {
                if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                    $unsafe = true;

                    return NodeVisitor::STOP_TRAVERSAL;
                }

                $keys[] = $item->key->value;
            }

            return null;
        });

        if ($unsafe) {
            return 'unsafe';
        }

        return array_values(array_unique($keys));
    }

    /**
     * Returns the `validate()` / `validateOnly()` rules-arg when `$call`
     * matches that shape, `null` when the rules slot is empty (non-hybrid
     * use), or `false` when `$call` isn't one of the two methods at all.
     */
    private function extractRulesArgFromValidateCall(MethodCall $call): Arg|false|null
    {
        if ($this->isName($call->name, 'validate')) {
            $positionalIndex = 0;
        } elseif ($this->isName($call->name, 'validateOnly')) {
            $positionalIndex = 1;
        } else {
            return false;
        }

        // Named args (`validate(rules: [...], messages: [...])` or
        // `validateOnly(field: 'x', rules: [...])`) take precedence over the
        // positional index. Codex review (2026-04-24) caught that
        // positional-only extraction could inspect the `messages` or
        // `attributes` array instead of `rules`, seeding the partial-overlap
        // skip set with non-rule keys like `'title.required'`.
        foreach ($call->args as $arg) {
            if (! $arg instanceof Arg) {
                continue;
            }

            if ($arg->name instanceof Identifier && $arg->name->toString() === 'rules') {
                return $arg;
            }
        }

        $candidate = $call->args[$positionalIndex] ?? null;

        if (! $candidate instanceof Arg) {
            return null;
        }

        // If the positional slot is actually a reordered named arg for a
        // different role, treat as "no positional rules arg".
        if ($candidate->name instanceof Identifier && $candidate->name->toString() !== 'rules') {
            return null;
        }

        return $candidate;
    }

    /**
     * Peel an accepted wrapper off `$this->validate($arg)` to reach the
     * underlying `Array_` literal. Two shapes are accepted:
     *
     *   - Direct `Array_` literal: `$this->validate(['title' => ...])`.
     *   - `RuleSet::compileToArrays($literalArray)` wrap (collectiq's
     *     idiom to pass fluent rules through the compile step).
     *
     * Anything else — `array_merge(...)`, bare variable, method call,
     * property fetch, ternary, match, concatenation — returns `null` and
     * the caller classifies the class as unsafe.
     */
    private function extractArrayLiteralFromValidateArg(Expr $expr): ?Array_
    {
        if ($expr instanceof Array_) {
            return $expr;
        }

        if (! $expr instanceof StaticCall) {
            return null;
        }

        if (! $expr->class instanceof Name || ! $expr->name instanceof Identifier) {
            return null;
        }

        if ($this->getName($expr->class) !== RuleSet::class) {
            return null;
        }

        if ($expr->name->toString() !== 'compileToArrays') {
            return null;
        }

        if (count($expr->args) !== 1 || ! $expr->args[0] instanceof Arg) {
            return null;
        }

        $inner = $expr->args[0]->value;

        return $inner instanceof Array_ ? $inner : null;
    }
}
