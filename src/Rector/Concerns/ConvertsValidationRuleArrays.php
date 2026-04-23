<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector\Concerns;

use BackedEnum;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;

/**
 * Array-form validation rule conversion: turns `['required', 'string', 'max:255']`
 * (and shapes with `Rule::` objects, `Password::min()` chains, email rule objects,
 * conditional tuples, etc.) into FluentRule method chains.
 *
 * Composes `ConvertsValidationRuleStrings` so that users of this trait also have
 * the full string-form surface (rule-string tokens, modifier dispatch, factory
 * construction, `$needsFluentRuleImport` state). Array-specific helpers live here;
 * string-specific helpers stay on the composed trait. The split keeps each file
 * focused on a single conversion concern while preserving a single place where
 * the import-tracking state lives.
 *
 * Entry point: `convertArrayToFluentRule(Array_ $rulesArray): ?Expr`.
 *
 * @phpstan-require-extends AbstractRector
 */
trait ConvertsValidationRuleArrays
{
    use ConvertsValidationRuleStrings;

    /**
     * Constructor-form rule-object classes whose `new X(...)` shape in
     * `#[Validate([...])]` attributes maps onto an existing passthrough
     * chain method (`Rule::unique(...)` → `->unique(...)`). Mapped only
     * when `inAttributeContext` is true; see `convertNewRuleObjectPassthrough`.
     *
     * @var array<string, string>
     */
    private const array NEW_RULE_OBJECT_PASSTHROUGH = [
        Unique::class => 'unique',
        Exists::class => 'exists',
    ];

    /** @var list<string> */
    private const array RULE_PASSTHROUGH_METHODS = [
        'in',
        'notIn',
        'unique',
        'exists',
        'enum',
        'contains',
        'doesntContain',
    ];

    /** @var list<string> */
    private const array RULE_FACTORY_METHODS = [
        'string',
        'numeric',
        'date',
        'email',
        'file',
        'imageFile',
    ];

    /** @var array<string, string> */
    private const array RULE_FACTORY_TYPE_MAP = [
        'string' => 'string',
        'numeric' => 'numeric',
        'date' => 'date',
        'email' => 'email',
        'file' => 'file',
        'imageFile' => 'image',
    ];

    /** @var list<string> */
    private const array PASSWORD_CHAIN_METHODS = [
        'letters',
        'mixedCase',
        'numbers',
        'symbols',
        'uncompromised',
        'max',
    ];

    /** @var list<string> */
    private const array EMAIL_CHAIN_METHODS = [
        'rfcCompliant',
        'strict',
        'validateMxRecord',
        'preventSpoofing',
        'withNativeValidation',
    ];

    /**
     * Rules whose fluent signature is `string $field, ...$values`. Spread is
     * preservable only at position >= 2 — the `$field` parameter must be
     * statically present so the converted call can never arity-fail at
     * rules() construction when the runtime spread yields an empty list.
     *
     * @var list<string>
     */
    private const array FIELD_VALUES_VARIADIC_RULES = [
        'requiredIf', 'requiredUnless',
        'excludeIf', 'excludeUnless',
        'prohibitedIf', 'prohibitedUnless',
        'presentIf', 'presentUnless',
        'missingIf', 'missingUnless',
    ];

    /**
     * Rules whose fluent signature is `string ...$fields`. Spread is
     * preservable at any position >= 1 — entire parameter list is variadic.
     *
     * @var list<string>
     */
    private const array PURE_FIELDS_VARIADIC_RULES = [
        'requiredWith', 'requiredWithAll', 'requiredWithout', 'requiredWithoutAll',
        'presentWith', 'presentWithAll',
        'missingWith', 'missingWithAll',
        'prohibits',
    ];

    /**
     * Per-type whitelists of methods safe to pass through from Rule:: factory chains.
     *
     * @var array<string, list<string>>
     */
    private const array FACTORY_CHAIN_WHITELISTS = [
        'string' => ['alpha', 'alphaDash', 'alphaNumeric', 'ascii', 'between', 'doesntEndWith', 'doesntStartWith', 'endsWith', 'exactly', 'lowercase', 'max', 'min', 'startsWith', 'uppercase'],
        'numeric' => ['between', 'decimal', 'different', 'digits', 'digitsBetween', 'greaterThan', 'greaterThanOrEqualTo', 'lessThan', 'lessThanOrEqualTo', 'max', 'maxDigits', 'min', 'minDigits', 'multipleOf', 'same', 'exactly'],
        'date' => ['format', 'beforeToday', 'afterToday', 'todayOrBefore', 'todayOrAfter', 'before', 'after', 'beforeOrEqual', 'afterOrEqual', 'between', 'betweenOrEqual', 'dateEquals', 'same', 'different'],
        'email' => ['rfcCompliant', 'strict', 'validateMxRecord', 'preventSpoofing', 'withNativeValidation', 'max', 'confirmed', 'same', 'different'],
        'file' => ['min', 'max', 'between', 'exactly', 'extensions', 'mimes', 'mimetypes'],
        'image' => ['min', 'max', 'between', 'exactly', 'extensions', 'mimes', 'mimetypes', 'allowSvg', 'width', 'height', 'minWidth', 'maxWidth', 'minHeight', 'maxHeight', 'ratio'],
    ];

    /**
     * Set by `convertArrayToFluentRule` for the duration of one array
     * conversion. Gates the constructor-form rule-object recognition
     * (`new Password(...)`, `new Rule\Unique(...)`, `new Rule\Exists(...)`):
     * those shapes are only used inside `#[Validate([...])]` attributes
     * because const-expr forbids the static-factory form in attribute args.
     * Detecting them globally would risk silently rewriting intentionally
     * constructor-form code in regular `rules()` arrays — scope leak raised
     * when this conversion was originally parked. The parent rector knows
     * its own context (attribute vs method), so passing it in as state is
     * cheaper and more reliable than walking `AttributeKey::PARENT_NODE`
     * (which Rector 2.x no longer populates by default).
     */
    private bool $inAttributeContext = false;

    /**
     * 1.19.0 sibling-token promotion (array-form): if the resolved type
     * has any promoting modifier siblings (`['string', 'ipv4']` →
     * `ipv4` factory), return the new type + the promoter's index.
     * Returns null when no promotion applies.
     *
     * @return array{type: string, index: int|string}|null
     */
    private function tryPromoteToSiblingFactory(string $type, Array_ $rulesArray): ?array
    {
        if (! isset(self::TYPE_PROMOTING_MODIFIERS[$type])) {
            return null;
        }

        $promoters = self::TYPE_PROMOTING_MODIFIERS[$type];

        foreach ($rulesArray->items as $index => $arrayItem) {
            if (! $arrayItem instanceof ArrayItem) {
                continue;
            }

            if (! $arrayItem->value instanceof String_) {
                continue;
            }

            $parsed = $this->parseRulePart($arrayItem->value->value);
            $normalized = $this->normalizeRuleName($parsed['name']);

            if (in_array($normalized, $promoters, true) && $parsed['args'] === null) {
                return ['type' => $normalized, 'index' => $index];
            }
        }

        return null;
    }

    /**
     * Pass 1: Pre-scan a rules array for the FIRST item that resolves
     * to a typed factory. Returns the resolved type + the position so
     * Pass 2 can fold the type item into the factory call. Returns
     * null when an item is malformed (non-ArrayItem, spread).
     *
     * Type detection order, first match wins:
     * 1. String token (`'string'`, `'email'`, …) → `TYPE_MAP` lookup.
     * 2. Password new/static call chain.
     * 3. `Rule::` factory chain.
     * 4. Email rule object (`Email::default()`, `new Email()` …).
     *
     * Subsequent items are processed in Pass 2 by `classifyAndChain`.
     *
     * Sibling-token promotion only applies to types resolved via plain
     * string token — `typeKind` carries that source so the promotion
     * pass can refuse to fire on chain-derived types (Password / Rule::
     * factory / Email rule object), which would otherwise lose the
     * extracted chain ops when their source item is superseded.
     *
     * @return array{type: ?string, typeIndex: int|string|null, typeKind: ?string, typeChainOps: list<array{name: string, args: list<Arg>}>, typeFactoryArgs: list<Arg>}|null
     */
    private function detectArrayRuleType(Array_ $rulesArray): ?array
    {
        $type = null;
        $typeIndex = null;
        $typeKind = null;

        /** @var list<array{name: string, args: list<Arg>}> */
        $typeChainOps = [];

        /** @var list<Arg> */
        $typeFactoryArgs = [];

        foreach ($rulesArray->items as $index => $arrayItem) {
            if (! $arrayItem instanceof ArrayItem || $arrayItem->unpack) {
                return null;
            }

            if ($arrayItem->value instanceof String_) {
                $parsed = $this->parseRulePart($arrayItem->value->value);
                $normalized = $this->normalizeRuleName($parsed['name']);

                if (isset(self::TYPE_MAP[$normalized])) {
                    $type = self::TYPE_MAP[$normalized];
                    $typeIndex = $index;
                    $typeKind = 'string_token';
                    break;
                }

                continue;
            }

            $passwordResult = $this->detectPasswordType($arrayItem->value);

            if ($passwordResult !== null) {
                $type = 'password';
                $typeIndex = $index;
                $typeKind = 'chain';
                $typeChainOps = $passwordResult['chainOps'];
                $typeFactoryArgs = $passwordResult['factoryArgs'];
                break;
            }

            $factoryResult = $this->detectRuleFactoryType($arrayItem->value);

            if ($factoryResult !== null) {
                $type = $factoryResult['type'];
                $typeIndex = $index;
                $typeKind = 'chain';
                $typeChainOps = $factoryResult['chainOps'];
                break;
            }

            $emailResult = $this->detectEmailType($arrayItem->value);

            if ($emailResult !== null) {
                $type = 'email';
                $typeIndex = $index;
                $typeKind = 'chain';
                $typeChainOps = $emailResult['chainOps'];
                break;
            }
        }

        return [
            'type' => $type,
            'typeIndex' => $typeIndex,
            'typeKind' => $typeKind,
            'typeChainOps' => $typeChainOps,
            'typeFactoryArgs' => $typeFactoryArgs,
        ];
    }

    private function convertArrayToFluentRule(Array_ $rulesArray, bool $inAttributeContext = false): ?Expr
    {
        $this->inAttributeContext = $inAttributeContext;

        $detection = $this->detectArrayRuleType($rulesArray);

        if ($detection === null) {
            return null;
        }

        $type = $detection['type'];
        $typeIndex = $detection['typeIndex'];
        $typeKind = $detection['typeKind'];
        $typeChainOps = $detection['typeChainOps'];
        $typeFactoryArgs = $detection['typeFactoryArgs'];

        // 1.19.0 sibling-token promotion (array-form): if the resolved
        // type has any promoting modifier siblings (`['string', 'ipv4']`
        // → `ipv4` factory), swap before the factory build. Same rationale
        // as the string-form promotion in `convertStringToFluentRule`:
        // converters must emit the final factory form directly because
        // SIMPLIFY isn't in the default set list. Only fires when type
        // came from a string-token match (chain-derived types like
        // `password`/`email` aren't in `TYPE_PROMOTING_MODIFIERS`).
        $supersededTypeIndex = null;

        // Promotion only fires when the type came from a plain string
        // token (`'string'` / `'array'`) — chain-derived types
        // (`Rule::string()->alpha()`, `new Password(...)`, `Email::default()`)
        // carry typeChainOps that get applied at typeIndex in Pass 2;
        // superseding that index would silently drop the chain ops.
        if ($type !== null && $typeKind === 'string_token') {
            $promoted = $this->tryPromoteToSiblingFactory($type, $rulesArray);

            if ($promoted !== null) {
                $supersededTypeIndex = $typeIndex;
                $type = $promoted['type'];
                $typeIndex = $promoted['index'];
            }
        }

        // Build root: FluentRule::type() with optional factory args (e.g., password($n))
        $resolvedType = $type ?? 'field';
        $expr = $this->buildFluentRuleFactory($resolvedType, $typeFactoryArgs);

        // Pass 2: Build chain in array order, inserting type chain ops at their position
        foreach ($rulesArray->items as $index => $arrayItem) {
            if (! $arrayItem instanceof ArrayItem) {
                return null;
            }

            // The original type token (now superseded by a promoter)
            // is dropped from the chain — its semantic role is owned
            // by the promoted factory. Skip without invoking
            // classifyAndChain to avoid the escape-hatch fallback.
            if ($index === $supersededTypeIndex) {
                continue;
            }

            // At the type element's position, apply its chain ops (from Password/Rule factory)
            if ($index === $typeIndex) {
                foreach ($typeChainOps as $op) {
                    $expr = new MethodCall($expr, new Identifier($op['name']), $op['args']);
                }

                continue;
            }

            $result = $this->classifyAndChain($expr, $arrayItem->value, $resolvedType);

            if (! $result instanceof Expr) {
                return null;
            }

            $expr = $result;
        }

        return $expr;
    }

    // ─── Element classification ──────────────────────────────────────────

    private function classifyAndChain(Expr $expr, Expr $value, string $type): ?Expr
    {
        if ($value instanceof String_) {
            $parsed = $this->parseRulePart($value->value);
            $normalized = $this->normalizeRuleName($parsed['name']);

            // If modifier isn't valid for this type, use ->rule('name:args') escape hatch
            if (! $this->isModifierValidForType($type, $normalized)) {
                return $this->wrapInRuleCall($expr, $value);
            }

            $methodCall = $this->buildModifierCall($expr, $normalized, $parsed['args']);

            // Unknown modifier → escape hatch instead of bail
            return $methodCall ?? $this->wrapInRuleCall($expr, $value);
        }

        if ($value instanceof StaticCall) {
            return $this->classifyStaticCall($expr, $value, $type);
        }

        // MethodCall: could be a chained Rule::unique/exists or Password chain
        if ($value instanceof MethodCall) {
            return $this->classifyMethodCall($expr, $value);
        }

        // Nested array tuple: ['required_if', $field, $value] or ['max', $expr]
        if ($value instanceof Array_) {
            return $this->classifyArrayTuple($expr, $value, $type);
        }

        if ($value instanceof New_) {
            // Attribute context: `new Rule\Unique(...)` / `new Rule\Exists(...)`
            // are the ctor-form equivalents of the `Rule::unique(...)` /
            // `Rule::exists(...)` static calls that `classifyStaticCall`
            // already folds into `->unique(...)` / `->exists(...)`. Same
            // scope-leak concern as Password: only convert when we know
            // the array is inside `#[Validate([...])]`.
            if ($this->inAttributeContext) {
                $converted = $this->convertNewRuleObjectPassthrough($expr, $value, $type);

                if ($converted instanceof MethodCall) {
                    return $converted;
                }
            }

            return $this->wrapInRuleCall($expr, $value);
        }

        if ($value instanceof Closure || $value instanceof ArrowFunction) {
            return $this->wrapInRuleCall($expr, $value);
        }

        return null;
    }

    private function classifyStaticCall(Expr $expr, StaticCall $staticCall, string $type): ?Expr
    {
        $className = $this->getName($staticCall->class);
        $methodName = $this->getName($staticCall->name);

        if ($methodName === null) {
            return null;
        }

        // Rule:: passthrough methods (in, notIn, unique, exists, enum, contains, doesntContain)
        if ($className === Rule::class
            && in_array($methodName, self::RULE_PASSTHROUGH_METHODS, true)) {
            return $this->convertRulePassthrough($expr, $staticCall, $methodName, $type);
        }

        // Any other Rule:: or non-Rule static call → wrap in ->rule()
        return $this->wrapInRuleCall($expr, $staticCall);
    }

    /**
     * Convert a nested array tuple like ['required_if', $field, $value] to a fluent method call.
     * Conditional rules with safe args → ->requiredIf($field, $value).
     * Tuples with unsafe args (enums, complex expressions) → bail on entire rule array.
     * Other tuples with safe args → ->rule(['name', $arg1]) escape hatch.
     */
    private function classifyArrayTuple(Expr $expr, Array_ $tuple, string $type): ?Expr
    {
        if ($tuple->items === []) {
            return null;
        }

        $firstItem = $tuple->items[0];

        if (! $firstItem instanceof ArrayItem || ! $firstItem->value instanceof String_) {
            return null; // Bail — can't determine rule name
        }

        $ruleName = $this->normalizeRuleName($firstItem->value->value);
        $spreadResult = $this->classifyTupleSpread($tuple, $ruleName);

        if ($spreadResult === null) {
            return null;
        }

        $hasSpread = $spreadResult['hasSpread'];

        // Check if all args are safe for conversion (strings, variables, concatenations)
        // Tuples with enum constants, method calls, etc. can't be safely serialized
        // by either the fluent API or ->rule() — bail on the entire rule array.
        if (! $this->allTupleArgsSafe($tuple)) {
            return null;
        }

        // Conditional rules: ['required_if', $field, $value] → ->requiredIf($field, $value)
        if (in_array($ruleName, self::COMMA_SEPARATED_ARGS_RULES, true)
            && $this->isModifierValidForType($type, $ruleName)) {
            $args = [];

            for ($i = 1, $count = count($tuple->items); $i < $count; ++$i) {
                /** @var ArrayItem $item */
                $item = $tuple->items[$i];
                // Skip adaptEnumArg on spread targets: a spread-target
                // ClassConstFetch (e.g. `Class::ARRAY_CONST`) must be an
                // iterable constant, not a BackedEnum case — wrapping it
                // in ->value would break the emitted code.
                $value = $item->unpack ? $item->value : $this->adaptEnumArg($item->value);
                $args[] = new Arg($value, byRef: false, unpack: $item->unpack);
            }

            return new MethodCall($expr, new Identifier($ruleName), $args);
        }

        // Defense-in-depth: spread only survives the COMMA_SEPARATED emit
        // arm. If we reach here with $hasSpread, the escape-hatch paths
        // below (->rule() rebuild, single-modifier lowering) cannot safely
        // reconstruct the spread, so bail.
        if ($hasSpread) {
            return null;
        }

        // Other tuples with safe args: ['max', '50'] → ->rule(['max', '50'])
        // But if the tuple contains foreign class constants (BackedEnums),
        // ->rule() would fail because it implode()s the params. Bail instead.
        if ($this->tupleHasForeignClassConst($tuple)) {
            return null;
        }

        // Try to lower the tuple directly to a fluent method call
        // (['max', 65535] → ->max(65535), ['between', 3, 100] → ->between(3, 100)).
        // Only proceed when the rule is valid for the factory type; otherwise
        // the tuple falls through to the ->rule() escape hatch below.
        if ($this->isModifierValidForType($type, $ruleName)) {
            $argExprs = [];

            for ($i = 1, $count = count($tuple->items); $i < $count; ++$i) {
                /** @var ArrayItem $item */
                $item = $tuple->items[$i];
                $argExprs[] = $item->value;
            }

            $fluentCall = $this->buildModifierCallFromTupleExprArgs($expr, $ruleName, $argExprs);

            if ($fluentCall instanceof MethodCall) {
                return $fluentCall;
            }
        }

        return $this->wrapInRuleCall($expr, $tuple);
    }

    private function tupleHasForeignClassConst(Array_ $tuple): bool
    {
        for ($i = 1, $count = count($tuple->items); $i < $count; ++$i) {
            $item = $tuple->items[$i];

            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($item->value instanceof ClassConstFetch
                && $item->value->class instanceof Name
                && ! in_array(strtolower($item->value->class->toString()), ['self', 'static', 'parent'], true)
                && $this->getName($item->value->name) !== 'class') {
                return true;
            }
        }

        return false;
    }

    private function classifyMethodCall(Expr $expr, MethodCall $methodCall): Expr
    {
        // Try to convert chained Rule::unique/exists to fluent callback form
        $dbResult = $this->convertChainedDatabaseRule($expr, $methodCall);

        if ($dbResult instanceof Expr) {
            return $dbResult;
        }

        // All other MethodCall chains → wrap in ->rule()
        return $this->wrapInRuleCall($expr, $methodCall);
    }

    // ─── Password chain detection ────────────────────────────────────────

    /**
     * Detect if an expression is an Email rule object and extract chain ops.
     * Matches: `Email::default()`, `new Email()`, `Email::default()->strict()`, `(new Email())->rfcCompliant()`
     *
     * @return array{chainOps: list<array{name: string, args: list<Arg>}>}|null
     */
    private function detectEmailType(Expr $expr): ?array
    {
        $chainCalls = [];
        $root = $expr;

        // Unwrap method chain
        while ($root instanceof MethodCall) {
            $methodName = $this->getName($root->name);

            if ($methodName === null || ! in_array($methodName, self::EMAIL_CHAIN_METHODS, true)) {
                return null; // Unknown method in chain → bail
            }

            /** @var list<Arg> $args */
            $args = $root->args;
            array_unshift($chainCalls, ['name' => $methodName, 'args' => $args]);
            $root = $root->var;
        }

        // Root must be Email::default()/::required()/::sometimes() OR new Email(...)
        if ($root instanceof StaticCall) {
            if ($this->getName($root->class) !== Email::class) {
                return null;
            }

            $factoryMethod = $this->getName($root->name);
            $staticFactories = ['default', 'required', 'sometimes'];

            if (! in_array($factoryMethod, $staticFactories, true)) {
                return null;
            }

            $chainOps = match ($factoryMethod) {
                'required' => [['name' => 'required', 'args' => []]],
                'sometimes' => [['name' => 'sometimes', 'args' => []]],
                default => [],
            };

            return ['chainOps' => [...$chainOps, ...$chainCalls]];
        }

        if ($root instanceof New_) {
            if (! $root->class instanceof Name) {
                return null;
            }

            if ($this->getName($root->class) !== Email::class) {
                return null;
            }

            return ['chainOps' => $chainCalls];
        }

        return null;
    }

    /**
     * Detect if an expression is a Password chain and extract type + chain ops + factory args.
     *
     * @return array{chainOps: list<array{name: string, args: list<Arg>}>, factoryArgs: list<Arg>}|null
     */
    private function detectPasswordType(Expr $expr): ?array
    {
        // `new Password($n)` inside `#[Validate([...])]`: const-expr forbids
        // `Password::min($n)` in attribute args, so consumers reach for the
        // constructor. Map it back to the same factory shape we produce for
        // the static-factory form. Gated on `inAttributeContext` so
        // `rules()`-array code that uses the constructor form intentionally
        // isn't silently rewritten.
        if ($expr instanceof New_ && $this->inAttributeContext) {
            return $this->detectPasswordNewExpr($expr);
        }

        $unwrapped = $this->unwrapMethodChain($expr);

        if ($unwrapped === null) {
            if (! $expr instanceof StaticCall) {
                return null;
            }

            $unwrapped = ['root' => $expr, 'calls' => []];
        }

        $root = $unwrapped['root'];

        if ($this->getName($root->class) !== Password::class) {
            return null;
        }

        $factoryMethod = $this->getName($root->name);

        if (! in_array($factoryMethod, ['min', 'default', 'required', 'sometimes'], true)) {
            return null;
        }

        // Factory args: Password::min($n) → FluentRule::password($n)
        $factoryArgs = [];

        if ($factoryMethod === 'min' && $root->args !== [] && $root->args[0] instanceof Arg) {
            $factoryArgs = [$root->args[0]];
        }

        // Password::required() / ::sometimes() → chain ops
        $chainOps = match ($factoryMethod) {
            'required' => [['name' => 'required', 'args' => []]],
            'sometimes' => [['name' => 'sometimes', 'args' => []]],
            default => [],
        };

        // Walk chain methods
        foreach ($unwrapped['calls'] as $call) {
            if ($call['name'] === 'rules') {
                return null; // Bail — dynamic rules
            }

            if (! in_array($call['name'], self::PASSWORD_CHAIN_METHODS, true)) {
                return null; // Unknown method → bail
            }

            $chainOps[] = $call;
        }

        return ['chainOps' => $chainOps, 'factoryArgs' => $factoryArgs];
    }

    // ─── Rule:: factory chain detection ──────────────────────────────────

    /**
     * Detect if an expression is a Rule:: factory chain and extract type + chain ops.
     *
     * @return array{type: string, chainOps: list<array{name: string, args: list<Arg>}>}|null
     */
    private function detectRuleFactoryType(Expr $expr): ?array
    {
        $unwrapped = $this->unwrapMethodChain($expr);

        if ($unwrapped === null) {
            if (! $expr instanceof StaticCall) {
                return null;
            }

            $unwrapped = ['root' => $expr, 'calls' => []];
        }

        $root = $unwrapped['root'];

        if ($this->getName($root->class) !== Rule::class) {
            return null;
        }

        $factoryMethod = $this->getName($root->name);

        if ($factoryMethod === null || ! in_array($factoryMethod, self::RULE_FACTORY_METHODS, true)) {
            return null;
        }

        $type = self::RULE_FACTORY_TYPE_MAP[$factoryMethod];
        $chainOps = [];

        // Handle imageFile($allowSvg) → image() + allowSvg()
        if ($factoryMethod === 'imageFile' && ($root->args !== [] && $root->args[0] instanceof Arg && $this->isTrueValue($root->args[0]->value))) {
            $chainOps[] = ['name' => 'allowSvg', 'args' => []];
        }

        // Walk chain methods with per-type whitelist. $type at this point is
        // one of the TYPE_MAP values ('string', 'numeric', 'date', 'email',
        // 'file', 'image'), all of which are keys on FACTORY_CHAIN_WHITELISTS.
        $whitelist = self::FACTORY_CHAIN_WHITELISTS[$type];

        foreach ($unwrapped['calls'] as $call) {
            $result = $this->applyFactoryChainCall($call, $type, $whitelist);

            if ($result === null) {
                return null;
            }

            $type = $result['type'];

            if ($result['op'] !== null) {
                $chainOps[] = $result['op'];
            }
        }

        return ['type' => $type, 'chainOps' => $chainOps];
    }

    /**
     * Classify one call in a Rule:: factory chain. Returns the (possibly
     * updated) type and the chain op to append (or null if the call only
     * mutates type without emitting an op, e.g. `Rule::numeric()->integer()`
     * which upgrades the type but doesn't append a method call).
     *
     * @param  array{name: string, args: list<Arg>}  $call
     * @param  list<string>  $whitelist
     * @return array{type: string, op: array{name: string, args: list<Arg>}|null}|null
     */
    private function applyFactoryChainCall(array $call, string $type, array $whitelist): ?array
    {
        // Rule::numeric()->integer() → FluentRule::integer()
        if ($type === 'numeric' && $call['name'] === 'integer') {
            if ($call['args'] !== []) {
                return null; // integer(true) for strict mode → bail
            }

            return ['type' => 'integer', 'op' => null];
        }

        if (! in_array($call['name'], $whitelist, true)) {
            return null; // Unknown method → bail
        }

        // Adapt extensions() array arg to individual args for variadic
        if ($call['name'] === 'extensions' && $this->hasArrayArg($call['args'])) {
            $adapted = $this->unpackArrayArg($call['args']);

            if ($adapted === null) {
                return null;
            }

            return ['type' => $type, 'op' => ['name' => 'extensions', 'args' => $adapted]];
        }

        return ['type' => $type, 'op' => $call];
    }

    // ─── Chained database rules ─────────────────────────────────────────

    /**
     * Convert Rule::unique('table')->ignore($id) to ->unique('table', null, fn ($rule) => $rule->ignore($id)).
     * Returns null if the expression is not a chained database rule.
     */
    private function convertChainedDatabaseRule(Expr $chain, MethodCall $methodCall): ?Expr
    {
        $unwrapped = $this->unwrapMethodChain($methodCall);

        if ($unwrapped === null) {
            return null;
        }

        $root = $unwrapped['root'];
        $calls = $unwrapped['calls'];

        if ($this->getName($root->class) !== Rule::class) {
            return null;
        }

        $rootMethod = $this->getName($root->name);

        if ($rootMethod !== 'unique' && $rootMethod !== 'exists') {
            return null;
        }

        if ($calls === []) {
            return null; // No chain — handled by passthrough
        }

        // Extract table and column args from the root StaticCall
        $fluentArgs = [];

        // First arg: table (required)
        if ($root->args === [] || ! $root->args[0] instanceof Arg) {
            return null;
        }

        $fluentArgs[] = $root->args[0];

        // Second arg: column (optional — pass null if absent to make room for callback)
        if (isset($root->args[1]) && $root->args[1] instanceof Arg) {
            $fluentArgs[] = $root->args[1];
        } else {
            $fluentArgs[] = new Arg(new ConstFetch(new Name('null')));
        }

        // Build the arrow function body: $rule->method1(...)->method2(...)
        $ruleVar = new Variable('rule');
        $callbackBody = $ruleVar;

        foreach ($calls as $call) {
            $callbackBody = new MethodCall(
                $callbackBody,
                new Identifier($call['name']),
                $call['args'],
            );
        }

        // Build: fn ($rule) => $rule->method1(...)->method2(...)
        $arrowFunction = new ArrowFunction([
            'params' => [new Param($ruleVar)],
            'expr' => $callbackBody,
        ]);

        $fluentArgs[] = new Arg($arrowFunction);

        return new MethodCall($chain, new Identifier($rootMethod), $fluentArgs);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * Unwrap a MethodCall chain into root StaticCall + ordered method calls.
     *
     * @return array{root: StaticCall, calls: list<array{name: string, args: list<Arg>}>}|null
     */
    private function unwrapMethodChain(Expr $expr): ?array
    {
        if (! $expr instanceof MethodCall) {
            return null;
        }

        /** @var list<array{name: string, args: list<Arg>}> */
        $calls = [];
        $current = $expr;

        while ($current instanceof MethodCall) {
            $name = $this->getName($current->name);

            if ($name === null) {
                return null;
            }

            /** @var list<Arg> $args */
            $args = $current->args;
            array_unshift($calls, ['name' => $name, 'args' => $args]);
            $current = $current->var;
        }

        if (! $current instanceof StaticCall) {
            return null;
        }

        return ['root' => $current, 'calls' => $calls];
    }

    /**
     * Types that have HasEmbeddedRules (in, notIn, unique, exists, enum).
     *
     * @var list<string>
     */
    private const array TYPES_WITH_EMBEDDED_RULES = [
        'string', 'email', 'numeric', 'integer', 'date', 'field',
        'url', 'uuid', 'ulid', 'ip', // These map to StringRule which has HasEmbeddedRules
    ];

    private function convertRulePassthrough(Expr $expr, StaticCall $staticCall, string $methodName, string $type): MethodCall
    {
        // Check if this passthrough method exists on the resolved type
        $isEmbeddedRule = in_array($methodName, ['in', 'notIn', 'unique', 'exists', 'enum'], true);
        $isArrayRule = in_array($methodName, ['contains', 'doesntContain'], true);

        if ($isEmbeddedRule && ! in_array($type, self::TYPES_WITH_EMBEDDED_RULES, true)) {
            return $this->wrapInRuleCall($expr, $staticCall);
        }

        if ($isArrayRule && $type !== 'array') {
            return $this->wrapInRuleCall($expr, $staticCall);
        }

        foreach ($staticCall->args as $arg) {
            if (! $arg instanceof Arg) {
                return $this->wrapInRuleCall($expr, $staticCall);
            }

            if ($arg->value instanceof Closure || $arg->value instanceof ArrowFunction) {
                return $this->wrapInRuleCall($expr, $staticCall);
            }
        }

        /** @var list<Arg> $args */
        $args = $staticCall->args;

        return new MethodCall($expr, new Identifier($methodName), $args);
    }

    private function wrapInRuleCall(Expr $chain, Expr $ruleExpr): MethodCall
    {
        return new MethodCall($chain, new Identifier('rule'), [new Arg($ruleExpr)]);
    }

    /**
     * `new Password($n)` inside `#[Validate([...])]`: extract constructor args
     * as factory args so the array lowers to `FluentRule::password($n)->…`.
     * `Password::class` FQN check scopes this tightly — any other `new X()`
     * hits the escape hatch.
     *
     * @return array{chainOps: list<array{name: string, args: list<Arg>}>, factoryArgs: list<Arg>}|null
     */
    private function detectPasswordNewExpr(New_ $new): ?array
    {
        if (! $new->class instanceof Name) {
            return null;
        }

        if ($this->getName($new->class) !== Password::class) {
            return null;
        }

        foreach ($new->args as $arg) {
            if (! $arg instanceof Arg) {
                return null;
            }

            if ($arg->value instanceof Closure || $arg->value instanceof ArrowFunction) {
                return null;
            }
        }

        /** @var list<Arg> $factoryArgs */
        $factoryArgs = $new->args;

        return ['chainOps' => [], 'factoryArgs' => $factoryArgs];
    }

    /**
     * `new Rule\Unique(...)` / `new Rule\Exists(...)` in attribute context:
     * lower to the same `->unique(...)` / `->exists(...)` chain op that
     * `Rule::unique(...)` / `Rule::exists(...)` already produce via
     * `convertRulePassthrough`. Returns null when the ctor class isn't in
     * the mapped set, when the surrounding type doesn't support embedded
     * rules, or when args carry closures — caller falls back to `->rule()`.
     */
    private function convertNewRuleObjectPassthrough(Expr $chain, New_ $new, string $type): ?MethodCall
    {
        if (! $new->class instanceof Name) {
            return null;
        }

        $className = $this->getName($new->class);
        $methodName = self::NEW_RULE_OBJECT_PASSTHROUGH[$className] ?? null;

        if ($methodName === null) {
            return null;
        }

        if (! in_array($type, self::TYPES_WITH_EMBEDDED_RULES, true)) {
            return null;
        }

        foreach ($new->args as $arg) {
            if (! $arg instanceof Arg) {
                return null;
            }

            if ($arg->value instanceof Closure || $arg->value instanceof ArrowFunction) {
                return null;
            }
        }

        /** @var list<Arg> $args */
        $args = $new->args;

        return new MethodCall($chain, new Identifier($methodName), $args);
    }

    /**
     * Walk tuple items once, validating shape and classifying spread
     * against the target rule's fluent signature. Returns null to signal
     * "bail"; otherwise returns whether the tuple carries any spread that
     * the COMMA_SEPARATED emit arm must preserve.
     *
     * Signature categories and spread rules: see FIELD_VALUES_VARIADIC_RULES
     * and PURE_FIELDS_VARIADIC_RULES declarations above.
     *
     * @return array{hasSpread: bool}|null
     */
    private function classifyTupleSpread(Array_ $tuple, string $ruleName): ?array
    {
        $hasSpread = false;
        $firstSpreadIndex = null;

        foreach ($tuple->items as $index => $item) {
            if (! $item instanceof ArrayItem) {
                return null;
            }

            if (! $item->unpack) {
                continue;
            }

            // Rule-name position can never be spread — we need a String_ to
            // identify the rule.
            if ($index === 0) {
                return null;
            }

            $hasSpread = true;
            $firstSpreadIndex ??= $index;
        }

        if (! $hasSpread) {
            return ['hasSpread' => false];
        }

        if (in_array($ruleName, self::FIELD_VALUES_VARIADIC_RULES, true)) {
            // Category A: `string $field, ...$values` — $field must be
            // statically present. All-args spread risks an empty-list
            // PHP TypeError at rules() construction, diverging from the
            // array form's later Laravel ValidationException. Bail.
            return $firstSpreadIndex === 1 ? null : ['hasSpread' => true];
        }

        if (in_array($ruleName, self::PURE_FIELDS_VARIADIC_RULES, true)) {
            // Category B: `string ...$fields` — entire signature variadic.
            return ['hasSpread' => true];
        }

        // Categories C, D, and everything non-variadic: spread unsafe.
        return null;
    }

    /**
     * Check if all tuple arguments (index 1+) are safe for fluent method conversion.
     * Safe types: string literals, variables, and string concatenation (BinaryOp\Concat).
     * Unsafe: class constants (enums), method calls, ternaries, etc.
     */
    private function allTupleArgsSafe(Array_ $tuple): bool
    {
        for ($i = 1, $count = count($tuple->items); $i < $count; ++$i) {
            $item = $tuple->items[$i];

            if (! $item instanceof ArrayItem) {
                return false;
            }

            // Spread items carry runtime-only values — no static safety claim
            // possible. The signature-aware gate in classifyArrayTuple has
            // already confirmed the spread targets a variadic parameter that
            // accepts arbitrary values.
            if ($item->unpack) {
                continue;
            }

            if (! $this->isSafeTupleArg($item->value)) {
                return false;
            }
        }

        return true;
    }

    private function isSafeTupleArg(Expr $expr): bool
    {
        // String literals: 'value'
        if ($expr instanceof String_) {
            return true;
        }

        // Integer/float literals: 1, 0.5 — safely stringify via implode
        if ($expr instanceof Int_ || $expr instanceof Float_) {
            return true;
        }

        // Boolean constants: true, false, null
        if ($expr instanceof ConstFetch && $this->isNames($expr, ['true', 'false', 'null'])) {
            return true;
        }

        // Variables: $field
        if ($expr instanceof Variable) {
            return true;
        }

        // String concatenation: self::PREFIX . '.*.type'
        if ($expr instanceof Concat) {
            return $this->isSafeTupleArg($expr->left) && $this->isSafeTupleArg($expr->right);
        }

        // Class constants: `self::FIELD` (string constants) and `OtherClass::CASE` (BackedEnum cases)
        // We allow both — adaptEnumArg() wraps foreign-class constants in ->value at conversion time.
        if ($expr instanceof ClassConstFetch) {
            return true;
        }

        // Enum value access written explicitly: `Enum::CASE->value`. PHP-Parser
        // models PropertyFetch::$name as `Identifier|Expr` so dynamic access
        // (`$obj->$var`) can be represented — narrow to Identifier to keep the
        // match static.
        return $expr instanceof PropertyFetch
            && $expr->var instanceof ClassConstFetch
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'value';
    }

    /**
     * Adapt a tuple argument for use in a fluent conditional method.
     * Foreign class constants are wrapped in `->value` access only if the class is a BackedEnum.
     * Local self/static constants and non-enum class constants are passed as-is.
     *
     * Example: `InteractionType::PAUSE` → `InteractionType::PAUSE->value` (BackedEnum)
     * Example: `LocalizedEnum::CONSTANT` → `LocalizedEnum::CONSTANT` (not BackedEnum)
     * Example: `self::FIELD` → `self::FIELD` (unchanged)
     */
    private function adaptEnumArg(Expr $expr): Expr
    {
        if (! $expr instanceof ClassConstFetch) {
            return $expr;
        }

        if (! $expr->class instanceof Name) {
            return $expr;
        }

        $className = $expr->class->toString();

        // self/static/parent are local references — likely string constants
        if (in_array(strtolower($className), ['self', 'static', 'parent'], true)) {
            return $expr;
        }

        // 'class' constant fetches are always strings (Enum::class), not cases
        if ($this->getName($expr->name) === 'class') {
            return $expr;
        }

        // Foreign class constant — only add ->value if confirmed as BackedEnum
        // If the class is autoloadable, check; if not, assume BackedEnum (safe default)
        if (class_exists($className) && ! is_subclass_of($className, BackedEnum::class)) {
            return $expr;
        }

        return new PropertyFetch($expr, 'value');
    }

    private function isTrueValue(Expr $expr): bool
    {
        return $expr instanceof ConstFetch && $this->isName($expr, 'true');
    }

    /**
     * Check if the first arg is an Array_ node.
     *
     * @param  list<Arg>  $args
     */
    private function hasArrayArg(array $args): bool
    {
        return $args !== [] && $args[0] instanceof Arg && $args[0]->value instanceof Array_;
    }

    /**
     * Unpack an Array_ arg into individual string Arg nodes (for variadic params).
     *
     * @param  list<Arg>  $args
     * @return list<Arg>|null
     */
    private function unpackArrayArg(array $args): ?array
    {
        if (! $args[0] instanceof Arg || ! $args[0]->value instanceof Array_) {
            return null;
        }

        $result = [];

        foreach ($args[0]->value->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->value instanceof String_) {
                return null;
            }

            $result[] = new Arg($item->value);
        }

        return $result;
    }
}
