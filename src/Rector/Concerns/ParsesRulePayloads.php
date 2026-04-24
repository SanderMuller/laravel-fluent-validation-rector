<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use BackedEnum;
use Illuminate\Validation\Rule;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
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
     * Laravel rule-token names that lower to FluentRule conditional methods
     * with variadic-safe signatures. Split into two arity tiers:
     *
     * - Category A (`string $field, ...$values`): require tail arity >= 2
     *   so the `$field` slot is statically present and the rewrite can't
     *   arity-fail on an empty value list.
     * - Category B (`string ...$fields`): require tail arity >= 1.
     *
     * Excluded:
     * - Category C (`string $field, ?string $message`) — rewriting would
     *   reinterpret arg 2 as the error message instead of a value.
     * - Category D (`string $field`) — single-arg signature; extra tail
     *   args would TypeError after rewrite.
     *
     * @var list<string>
     */
    private const array COMMA_SEPARATED_FIELD_VALUES_RULES = [
        'required_if', 'required_unless',
        'exclude_if', 'exclude_unless',
        'prohibited_if', 'prohibited_unless',
        'present_if', 'present_unless',
        'missing_if', 'missing_unless',
    ];

    /**
     * Laravel rule-token names that are written as `->rule('<token>')` with
     * no arg tail and map to a zero-arg fluent method on the receiver
     * (`->rule('accepted')` → `->accepted()`). All are inherited from
     * `HasFieldModifiers` or `SelfValidates` so they exist on every typed
     * builder; `isMethodAvailable` still gates per-receiver to catch any
     * future divergence.
     *
     * `bail` excluded — hihaho 0.12.0 dogfood confirmed zero wild usage as a
     * `->rule('bail')` wrapper. Always authored as pipe-prefix (`'bail|…'`)
     * or chained `->bail()`.
     *
     * @var list<string>
     */
    private const array ZERO_ARG_RULE_TOKENS = [
        'accepted',
        'declined',
        'present',
        'prohibited',
        'nullable',
        'sometimes',
        'required',
        'filled',
    ];

    /** @var list<string> */
    private const array COMMA_SEPARATED_PURE_FIELDS_RULES = [
        'required_with', 'required_with_all', 'required_without', 'required_without_all',
        'present_with', 'present_with_all',
        'missing_with', 'missing_with_all',
        'prohibits',
        // `requiredArrayKeys(string ...$keys)` — ArrayRule-only; semantically
        // array-key validation, not field-dependency, but shares the variadic
        // string signature that this builder produces. Per-class method
        // allowlist in SimplifyRuleWrappersRector gates the receiver.
        'required_array_keys',
    ];

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

        // `Rule::enum(string $type, ?Closure $cb = null)` — arity 2 in
        // Laravel but only the type-arg form (`Rule::enum(X::class)`)
        // is migrate-safe. The optional callback can't be statically
        // threaded through `->enum(...)` without losing semantics, so
        // multi-arg facade calls bail.
        if ($name === 'enum' && count($args) !== 1) {
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

        if (in_array($name, ['gt', 'gte', 'lt', 'lte'], true)) {
            // 1.19.0 sign helpers — only fire when the arg is the
            // literal zero (string-form spelling: `'0'` or `'0.0'`).
            // Gate on the RAW token text BEFORE numeric coercion;
            // `literalForToken` would map `'00'` and `'-0'` to the
            // same `Int_(0)` node, broadening the match beyond the
            // intended exact-zero spelling. Result is a zero-arg call
            // (`->positive()`, not `->positive(0)`).
            return $this->isLiteralZeroToken($tail) ? [] : null;
        }

        // Zero-arg tokens written as `->rule('accepted')` / `->rule('nullable')`
        // etc. RULE_NAME_TO_METHOD maps the names to fluent methods; here the
        // only job is to confirm the token has no tail args and emit an empty
        // arg list. Excludes `bail` — peer review (hihaho 0.12.0 dogfood)
        // confirmed zero wild usage as a wrapper, only as pipe-prefix or
        // chained `->bail()`.
        if (in_array($name, self::ZERO_ARG_RULE_TOKENS, true)) {
            return $tail === '' ? [] : null;
        }

        // `enum` deliberately has no string-form support in v1 — the
        // class-string would need backslash-escape handling that's out
        // of scope. Returning null lets the rector silently no-op; the
        // user keeps the `->rule('enum:...')` escape hatch.
        return null;
    }

    /**
     * Whether the raw token text represents an exact literal zero in
     * Laravel rule-string spelling. Acceptable: `'0'`, `'0.0'`. Refused:
     * `'00'`, `'-0'`, `'+0'`, `'0.00'` — accepting them would broaden
     * the sign-helper rewrite beyond the documented exact-zero match.
     */
    private function isLiteralZeroToken(string $token): bool
    {
        return $token === '0' || $token === '0.0';
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

        if (in_array($name, self::COMMA_SEPARATED_FIELD_VALUES_RULES, true)) {
            return $this->buildCommaSeparatedArgsFromArrayItems($name, $tailItems, minTailArity: 2);
        }

        if (in_array($name, self::COMMA_SEPARATED_PURE_FIELDS_RULES, true)) {
            return $this->buildCommaSeparatedArgsFromArrayItems($name, $tailItems, minTailArity: 1);
        }

        return $this->buildArityArgsFromArrayItems($name, $tailItems);
    }

    /**
     * Build the arg list for a COMMA_SEPARATED conditional-rule rewrite
     * (e.g. `['required_if', 'field', 'value']` → `->requiredIf('field',
     * 'value')`). Every tail item must pass the strict static-safety
     * whitelist — overloaded fluent signatures (`Closure|bool|string
     * $field`) mean a dynamic expression could silently switch branches
     * after rewrite. BackedEnum cases in tail positions are auto-wrapped
     * with `->value` to match the fluent variadic signature
     * (`string|int|bool|BackedEnum ...$values`).
     *
     * @param  list<ArrayItem>  $tailItems
     * @return array{0: string, 1: list<Arg>}|null
     */
    private function buildCommaSeparatedArgsFromArrayItems(string $name, array $tailItems, int $minTailArity): ?array
    {
        if (count($tailItems) < $minTailArity) {
            return null;
        }

        $args = [];

        foreach ($tailItems as $item) {
            if ($item->key instanceof Expr || $item->byRef || $item->unpack) {
                return null;
            }

            if (! $this->isSafeCommaSeparatedArg($item->value)) {
                return null;
            }

            $args[] = new Arg($this->adaptEnumCaseArg($item->value));
        }

        return [$name, $args];
    }

    /**
     * Strict whitelist — statically-scalar expressions only. Mirrors the
     * `isSafeTupleArg` gate in `ConvertsValidationRuleArrays` used for the
     * array-form COMMA_SEPARATED lowering path. Kept in sync because both
     * rectors emit identical fluent-method calls for equivalent array shapes.
     */
    private function isSafeCommaSeparatedArg(Expr $expr): bool
    {
        if ($expr instanceof String_ || $expr instanceof Int_) {
            return true;
        }

        // Float_ rejected — fluent COMMA_SEPARATED value union is
        // `string|int|bool|BackedEnum`; `->requiredIf('field', 1.5)` would
        // TypeError after rewrite. `null` rejected for the same reason.
        if ($expr instanceof ConstFetch && $this->isNames($expr, ['true', 'false'])) {
            return true;
        }

        if ($expr instanceof Variable) {
            return true;
        }

        if ($expr instanceof Concat) {
            return $this->isSafeCommaSeparatedArg($expr->left) && $this->isSafeCommaSeparatedArg($expr->right);
        }

        if ($expr instanceof ClassConstFetch) {
            return true;
        }

        // `Enum::CASE->value` — PropertyFetch on a ClassConstFetch.
        return $expr instanceof PropertyFetch
            && $expr->var instanceof ClassConstFetch
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'value';
    }

    /**
     * Wrap bare BackedEnum cases in `->value` so the emitted call matches
     * the fluent variadic signature. Mirrors `adaptEnumArg` in
     * `ConvertsValidationRuleArrays`. Non-ClassConstFetch expressions pass
     * through unchanged; `self`/`static`/`parent` and `::class` stay as-is.
     */
    private function adaptEnumCaseArg(Expr $expr): Expr
    {
        if (! $expr instanceof ClassConstFetch) {
            return $expr;
        }

        if (! $expr->class instanceof Name) {
            return $expr;
        }

        $className = $expr->class->toString();

        if (in_array(strtolower($className), ['self', 'static', 'parent'], true)) {
            return $expr;
        }

        if ($expr->name instanceof Identifier && $expr->name->toString() === 'class') {
            return $expr;
        }

        // If the class is autoloadable and NOT a BackedEnum, don't wrap —
        // the constant is already a scalar.
        if (class_exists($className) && ! is_subclass_of($className, BackedEnum::class)) {
            return $expr;
        }

        return new PropertyFetch($expr, 'value');
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
        // Sign helpers: arity 1, but the single arg must be a literal
        // zero. Result is a zero-arg call (`->positive()`), so we
        // gate first then return `[]` args on success.
        if (in_array($name, ['gt', 'gte', 'lt', 'lte'], true)) {
            if (count($tailItems) !== 1) {
                return null;
            }

            $item = $tailItems[0];

            if ($item->key instanceof Expr || $item->byRef || $item->unpack) {
                return null;
            }

            return $this->isLiteralZeroArrayValue($item->value) ? [$name, []] : null;
        }

        $expectedArity = match ($name) {
            'min', 'max', 'size', 'regex', 'enum' => 1,
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

    /**
     * Whether the array-form arg literal represents an exact zero for
     * the sign-helper rewrite. Same exact-spelling policy as
     * `isLiteralZeroToken` for string-form: refuses `Int_(0)` produced
     * by coercion of `'00'` / `'-0'` is moot because we receive raw
     * AST nodes here, not coerced values. Accept `Int_(0)`,
     * `Float_(0.0)`, `String_('0')`, `String_('0.0')`.
     */
    private function isLiteralZeroArrayValue(Expr $value): bool
    {
        if ($value instanceof Int_) {
            return $value->value === 0;
        }

        if ($value instanceof Float_) {
            return $value->value === 0.0;
        }

        if ($value instanceof String_) {
            return $value->value === '0' || $value->value === '0.0';
        }

        return false;
    }
}
