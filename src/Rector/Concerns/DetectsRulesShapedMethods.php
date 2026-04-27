<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\ImageFile;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;

/**
 * Detect methods that hold validation rules under any name (not just
 * `rules()`). Used by the converter, grouping, and docblock-narrowing
 * rectors to widen their method-discovery beyond literal `rules()`.
 *
 * A method qualifies when ALL of these hold:
 *
 * 1. **Method body is `return [...];` only.** A single statement that
 *    returns a literal `Array_`. Multi-statement bodies, builder
 *    pipelines, conditional assembly, helper-method delegation — all
 *    rejected. Same single-return constraint other rectors in this
 *    package use.
 * 2. **Array is string-keyed.** All `ArrayItem` keys are literal
 *    `String_`. `ClassConstFetch` keys are rejected — they may resolve
 *    to int / enum / mixed at runtime (Eloquent attribute lookup tables,
 *    enum-keyed config maps) and the AST cannot prove the key type.
 *    List-shape arrays (`['required', 'string']`) are values inside a
 *    rule definition, not rules definitions themselves — never the
 *    top-level rules-array shape. Methods using `ClassConstFetch` keys
 *    in their rules array still convert via the literal `rules()` name
 *    path or the `#[FluentRules]` opt-in.
 * 3. **At least one item value is rule-shaped.** A value is rule-shaped
 *    when it matches any of:
 *    - A pipe-delimited string with at least one recognized rule name
 *      (`'required|string'`, `'nullable|email|max:255'`).
 *    - A `Rule::*()` static call (`Rule::unique('users')`).
 *    - A `FluentRule::*()` chain (already-converted methods stay
 *      detected — harmless idempotency).
 *    - A constructor-form rule object (`new Password(8)`,
 *      `new Unique('users')`).
 *    - An array literal containing any of the above (`['required',
 *      'string', Rule::unique(...)]`).
 *
 * The class-qualification gate (`QualifiesForRulesProcessing`) is the
 * primary safety boundary; this predicate runs INSIDE that gate. The
 * combined effect: only methods on FormRequest descendants / fluent-
 * validation-trait users / Livewire components / `#[FluentRules]`-
 * marked classes get the shape check.
 *
 * @internal
 *
 * @phpstan-require-extends AbstractRector
 */
trait DetectsRulesShapedMethods
{
    use NonRulesMethodNames;

    /**
     * Recognized Laravel rule names. A pipe-delimited string with at
     * least one segment matching any of these counts as a rule string.
     *
     * Sourced from Laravel's documented validation rules + the
     * package's 1.19.0 factory shortcuts. Kept flat (no per-category
     * grouping) to keep the per-token lookup at O(1) via `isset`.
     *
     * Drift risk: when Laravel adds a new rule name OR the package
     * adds a new fluent factory shortcut, this list must update.
     * Acceptable trade-off vs. round-tripping every candidate string
     * through `convertStringToFluentRule()` — that path mutates AST
     * state and re-does work the actual conversion will repeat.
     *
     * @var array<string, true>
     */
    private const array KNOWN_RULE_NAMES = [
        // Type / factory rules
        'accepted' => true, 'accepted_if' => true, 'active_url' => true,
        'after' => true, 'after_or_equal' => true, 'alpha' => true,
        'alpha_dash' => true, 'alpha_num' => true, 'array' => true,
        'ascii' => true, 'bail' => true, 'before' => true,
        'before_or_equal' => true, 'between' => true, 'bool' => true,
        'boolean' => true, 'confirmed' => true, 'contains' => true,
        'current_password' => true, 'date' => true, 'date_equals' => true,
        'date_format' => true, 'decimal' => true, 'declined' => true,
        'declined_if' => true, 'different' => true, 'digits' => true,
        'digits_between' => true, 'dimensions' => true, 'distinct' => true,
        'doesnt_end_with' => true, 'doesnt_start_with' => true, 'email' => true,
        'ends_with' => true, 'enum' => true, 'exclude' => true,
        'exclude_if' => true, 'exclude_unless' => true, 'exclude_with' => true,
        'exclude_without' => true, 'exists' => true, 'extensions' => true,
        'file' => true, 'filled' => true, 'gt' => true,
        'gte' => true, 'hex_color' => true, 'image' => true,
        'in' => true, 'in_array' => true, 'integer' => true,
        'int' => true, 'ip' => true, 'ipv4' => true,
        'ipv6' => true, 'json' => true, 'list' => true,
        'lt' => true, 'lte' => true, 'mac_address' => true,
        'macAddress' => true, 'max' => true, 'max_digits' => true,
        'mimes' => true, 'mimetypes' => true, 'min' => true,
        'min_digits' => true, 'missing' => true, 'missing_if' => true,
        'missing_unless' => true, 'missing_with' => true, 'missing_with_all' => true,
        'multiple_of' => true, 'not_in' => true, 'not_regex' => true,
        'nullable' => true, 'numeric' => true, 'password' => true,
        'present' => true, 'present_if' => true, 'present_unless' => true,
        'present_with' => true, 'present_with_all' => true, 'prohibited' => true,
        'prohibited_if' => true, 'prohibited_unless' => true, 'prohibits' => true,
        'regex' => true, 'required' => true, 'required_array_keys' => true,
        'required_if' => true, 'required_if_accepted' => true, 'required_if_declined' => true,
        'required_unless' => true, 'required_with' => true, 'required_with_all' => true,
        'required_without' => true, 'required_without_all' => true, 'same' => true,
        'size' => true, 'sometimes' => true, 'starts_with' => true,
        'string' => true, 'timezone' => true, 'ulid' => true,
        'unique' => true, 'uppercase' => true, 'lowercase' => true,
        'url' => true, 'uuid' => true,
        // 1.19.0 fluent factory shortcuts
        'activeUrl' => true, 'hexColor' => true,
    ];

    /**
     * The `#[FluentRules]` attribute FQN. String-referenced (not
     * `::class`) so PHPStan doesn't require the class to exist at
     * static-analysis time — `FluentRules` ships in newer
     * laravel-fluent-validation releases but is absent from earlier
     * versions still satisfying the package's `^1.0` constraint.
     */
    private const string FLUENT_RULES_ATTRIBUTE_FQN = FluentRules::class;

    /**
     * Returns true when the method carries the `#[FluentRules]`
     * attribute — the explicit per-method opt-in for non-`rules()`
     * rules-bearing methods. Lives here (alongside the auto-detect
     * shape check) so every consumer trait/rector can call it via the
     * single `DetectsRulesShapedMethods` import they already use, and
     * the gate logic ("name=rules || hasFluentRulesAttribute || (auto
     * && rules-shaped)") stays consistent across converter, grouping,
     * and docblock rectors.
     */
    private function hasFluentRulesAttribute(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->getName($attr->name) === self::FLUENT_RULES_ATTRIBUTE_FQN) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return true when the method body is shaped like a rules array.
     * See trait docblock for the qualifying signature.
     */
    private function isRulesShapedMethod(ClassMethod $method): bool
    {
        // Method-name denylist (Codex review catch). Eloquent `casts()`,
        // Laravel `messages()`/`attributes()`, and similar string-keyed
        // returns can satisfy the structural shape check by accident
        // when their values overlap with rule tokens. Bail before the
        // shape check so the converters never touch these methods.
        // Lookup is via the shared `NonRulesMethodNames` trait helper —
        // PHP method names are case-insensitive at runtime, so the
        // helper lowercases before checking against the (lowercase)
        // denylist.
        if ($this->isNonRulesMethodName($this->getName($method))) {
            return false;
        }

        if ($method->stmts === null || count($method->stmts) !== 1) {
            return false;
        }

        $stmt = $method->stmts[0];

        if (! $stmt instanceof Return_ || ! $stmt->expr instanceof Array_) {
            return false;
        }

        $array = $stmt->expr;

        if ($array->items === []) {
            return false;
        }

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                return false;
            }

            if (! $this->isStringLikeKey($item->key)) {
                return false;
            }
        }

        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem && $this->isRuleShapedValue($item->value)) {
                return true;
            }
        }

        return false;
    }

    private function isStringLikeKey(?Expr $key): bool
    {
        if (! $key instanceof Expr) {
            // null key = list-shape entry — disqualifies the array.
            return false;
        }

        // Only literal string keys count. `ClassConstFetch` keys
        // (`Status::ACTIVE => ...`) cannot be statically resolved to a
        // string at AST time — the const may be int- or enum-backed and
        // produce an int-keyed map (Eloquent attribute lookup tables,
        // enum-keyed config maps). Codex 2026-04-26 caught the gap:
        // accepting them as string-like risked auto-detect rewriting
        // those maps as rules. Narrow to literal `String_` only; methods
        // whose rules array uses class-const keys still convert via the
        // literal `rules()` name path or the `#[FluentRules]` opt-in.
        return $key instanceof String_;
    }

    /**
     * A value is rule-shaped when it matches any of:
     * - A pipe-delimited string whose first segment is a recognized rule.
     * - A `Rule::*()` static call (Illuminate\Validation\Rule).
     * - A `FluentRule::*()` chain (already converted; idempotent).
     * - A constructor-form rule object (`new Password(8)`,
     *   `new Unique('users')`, `new Exists('roles')`).
     * - An array literal containing any of the above.
     */
    private function isRuleShapedValue(Expr $value): bool
    {
        if ($value instanceof String_) {
            return $this->stringContainsKnownRuleToken($value->value);
        }

        if ($value instanceof StaticCall) {
            return $this->isKnownRuleFactoryStaticCall($value);
        }

        if ($value instanceof MethodCall) {
            return $this->isFluentRuleChainCall($value);
        }

        if ($value instanceof New_) {
            return $this->isKnownRuleConstructor($value);
        }

        if ($value instanceof Array_) {
            foreach ($value->items as $item) {
                if ($item instanceof ArrayItem && $this->isRuleShapedValue($item->value)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function stringContainsKnownRuleToken(string $value): bool
    {
        foreach (explode('|', $value) as $part) {
            $colonPos = strpos($part, ':');
            $ruleName = $colonPos === false ? $part : substr($part, 0, $colonPos);

            if (isset(self::KNOWN_RULE_NAMES[$ruleName])) {
                return true;
            }
        }

        return false;
    }

    private function isKnownRuleFactoryStaticCall(StaticCall $call): bool
    {
        if (! $call->class instanceof Name) {
            return false;
        }

        $className = $this->getName($call->class);

        return $className === Rule::class
            || $className === 'Rule';
    }

    private function isFluentRuleChainCall(MethodCall $call): bool
    {
        $current = $call;

        while ($current instanceof MethodCall) {
            $current = $current->var;
        }

        if (! $current instanceof StaticCall) {
            return false;
        }

        if (! $current->class instanceof Name || ! $current->name instanceof Identifier) {
            return false;
        }

        $className = $this->getName($current->class);

        return $className === FluentRule::class
            || $className === 'FluentRule';
    }

    private function isKnownRuleConstructor(New_ $node): bool
    {
        if (! $node->class instanceof Name) {
            return false;
        }

        $className = $this->getName($node->class);

        return in_array($className, [
            Password::class,
            Unique::class,
            Exists::class,
            In::class,
            NotIn::class,
            Enum::class,
            Dimensions::class,
            File::class,
            ImageFile::class,
            'Password', 'Unique', 'Exists', 'In', 'NotIn', 'Enum',
            'Dimensions', 'File', 'ImageFile',
        ], true);
    }
}
