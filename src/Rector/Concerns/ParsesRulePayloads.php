<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use Illuminate\Validation\Rule;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;

/**
 * Recognises the three `->rule(...)` payload shapes a v1
 * `SimplifyRuleWrappersRector` rewrite cares about — `Rule::name(args...)`
 * facade calls, `'name:args'` strings, and `['name', args...]` arrays —
 * into a `(name, list<Arg>)` tuple. Rule-token argument shapes (single int,
 * comma-separated values, regex pattern verbatim) live here too.
 *
 * @phpstan-require-extends AbstractRector
 */
trait ParsesRulePayloads
{
    /**
     * Parse a `->rule(...)` payload into `[ruleName, list<Arg>]` if it
     * matches one of the recognised shapes. Returns `null` for anything
     * else (variable, object instance, concatenation, etc.).
     *
     * @return array{0: string, 1: list<Arg>}|null
     */
    private function parseRulePayload(Expr $payload): ?array
    {
        if ($payload instanceof StaticCall) {
            return $this->parseRuleFacadeCall($payload);
        }

        if ($payload instanceof String_) {
            return $this->parseStringRule($payload->value);
        }

        if ($payload instanceof Array_) {
            return $this->parseArrayRule($payload);
        }

        return null;
    }

    /**
     * Recognise `Rule::name(args...)` against `Illuminate\Validation\Rule`.
     * Enforces `in`/`notIn` arity = 1 to avoid rewriting multi-arg
     * `Rule::in('a', 'b')` (Laravel ignores trailing args; native
     * `->in($values)` would `ArgumentCountError` on the same input).
     *
     * @return array{0: string, 1: list<Arg>}|null
     */
    private function parseRuleFacadeCall(StaticCall $call): ?array
    {
        if (! $call->name instanceof Identifier) {
            return null;
        }

        if (! $this->isObjectType($call->class, new ObjectType(Rule::class))) {
            return null;
        }

        $args = [];

        foreach ($call->args as $arg) {
            if (! $arg instanceof Arg || $arg->byRef || $arg->unpack) {
                return null;
            }

            $args[] = $arg;
        }

        $name = $call->name->toString();

        if (in_array($name, ['in', 'notIn'], true) && count($args) !== 1) {
            return null;
        }

        return [$name, $args];
    }

    /**
     * Parse `'name:args'` using a substring split on the FIRST `:` only.
     * Patterns may legitimately contain `:` (e.g. `regex:/^\w+:\d+$/`) so
     * this never re-splits the tail beyond rule-specific semantics.
     *
     * @return array{0: string, 1: list<Arg>}|null
     */
    private function parseStringRule(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }

        $colon = strpos($raw, ':');
        $name = $colon === false ? $raw : substr($raw, 0, $colon);
        $tail = $colon === false ? '' : substr($raw, $colon + 1);

        if ($name === '') {
            return null;
        }

        $args = $this->buildArgsFromStringTail($name, $tail);

        if ($args === null) {
            return null;
        }

        return [$name, $args];
    }

    /**
     * Build the rule-specific arg list from the post-`:` substring.
     * Per-rule shape decisions live here rather than at the call site.
     *
     * @return list<Arg>|null
     */
    private function buildArgsFromStringTail(string $name, string $tail): ?array
    {
        if (in_array($name, ['in', 'notIn', 'not_in'], true)) {
            return $this->buildInArgsFromTail($tail);
        }

        if (in_array($name, ['min', 'max', 'size'], true)) {
            return $tail === '' ? null : [new Arg($this->literalForToken($tail))];
        }

        if ($name === 'between') {
            return $this->buildBetweenArgsFromTail($tail);
        }

        if ($name === 'regex') {
            // Pattern may legitimately contain `:` and `,`; preserve the
            // entire substring after the first `:` verbatim. Bail on
            // empty pattern — `->regex('')` is meaningless.
            return $tail === '' ? null : [new Arg(new String_($tail))];
        }

        return null;
    }

    /** @return list<Arg>|null */
    private function buildInArgsFromTail(string $tail): ?array
    {
        if ($tail === '') {
            return null;
        }

        $items = [];

        foreach (explode(',', $tail) as $value) {
            $items[] = new ArrayItem(new String_($value));
        }

        return [new Arg(new Array_($items))];
    }

    /** @return list<Arg>|null */
    private function buildBetweenArgsFromTail(string $tail): ?array
    {
        $parts = explode(',', $tail);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return [
            new Arg($this->literalForToken($parts[0])),
            new Arg($this->literalForToken($parts[1])),
        ];
    }

    /**
     * Coerce a Laravel-rule-token argument substring to its AST literal.
     *
     * - Pure base-10 integers (incl. negative) → `Int_` so the typed-rule
     *   `int` parameter accepts them.
     * - Float-shaped numerics (incl. negative, e.g. `'1.5'`, `'-0.25'`)
     *   → `Float_` so `NumericRule::min(int|float)` accepts them. Without
     *   this, `'min:1.5'` rewrites would runtime-error.
     * - Everything else → `String_` so the runtime sees exactly what the
     *   user wrote (e.g. `'2mb'` for `FileRule::min` shorthand).
     */
    private function literalForToken(string $token): Expr
    {
        if ($token === '') {
            return new String_($token);
        }

        $unsigned = $token[0] === '-' ? substr($token, 1) : $token;

        if ($unsigned !== '' && ctype_digit($unsigned)) {
            return new Int_((int) $token);
        }

        // Float shape: optional sign + digits + `.` + digits, no exponent
        // (Laravel rule tokens don't use scientific notation).
        if (preg_match('/^-?\d+\.\d+$/', $token) === 1) {
            return new Float_((float) $token);
        }

        return new String_($token);
    }

    /**
     * Parse `['name', arg1, arg2, ...]` into a (name, list<Arg>) tuple.
     * Index 0 must be a String_ literal; spread items invalidate the shape.
     *
     * @return array{0: string, 1: list<Arg>}|null
     */
    private function parseArrayRule(Array_ $array): ?array
    {
        if ($array->items === [] || ! isset($array->items[0])) {
            return null;
        }

        $first = $array->items[0];

        if (! $first instanceof ArrayItem
            || ! $first->value instanceof String_
            || $first->key instanceof Expr
            || $first->byRef
            || $first->unpack) {
            return null;
        }

        $name = $first->value->value;

        if ($name === '') {
            return null;
        }

        $tailItems = array_values(array_slice($array->items, 1));

        if (in_array($name, ['in', 'notIn', 'not_in'], true)) {
            return $this->buildInArgsFromArrayItems($name, $tailItems);
        }

        return $this->buildArityArgsFromArrayItems($name, $tailItems);
    }

    /**
     * @param  list<ArrayItem>  $tailItems
     * @return array{0: string, 1: list<Arg>}|null
     */
    private function buildInArgsFromArrayItems(string $name, array $tailItems): ?array
    {
        if ($tailItems === []) {
            return null;
        }

        $items = [];

        foreach ($tailItems as $item) {
            if ($item->key instanceof Expr || $item->byRef || $item->unpack) {
                return null;
            }

            $items[] = new ArrayItem($item->value);
        }

        return [$name, [new Arg(new Array_($items))]];
    }

    /**
     * @param  list<ArrayItem>  $tailItems
     * @return array{0: string, 1: list<Arg>}|null
     */
    private function buildArityArgsFromArrayItems(string $name, array $tailItems): ?array
    {
        $expectedArity = match ($name) {
            'min', 'max', 'size', 'regex' => 1,
            'between' => 2,
            default => null,
        };

        if ($expectedArity === null || count($tailItems) !== $expectedArity) {
            return null;
        }

        $args = [];

        foreach ($tailItems as $item) {
            if ($item->key instanceof Expr || $item->byRef || $item->unpack) {
                return null;
            }

            $args[] = new Arg($item->value);
        }

        return [$name, $args];
    }
}
