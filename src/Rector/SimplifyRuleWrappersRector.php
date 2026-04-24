<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Float_;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\AcceptedRule;
use SanderMuller\FluentValidation\Rules\ArrayRule;
use SanderMuller\FluentValidation\Rules\BooleanRule;
use SanderMuller\FluentValidation\Rules\DateRule;
use SanderMuller\FluentValidation\Rules\EmailRule;
use SanderMuller\FluentValidation\Rules\FieldRule;
use SanderMuller\FluentValidation\Rules\FileRule;
use SanderMuller\FluentValidation\Rules\ImageRule;
use SanderMuller\FluentValidation\Rules\NumericRule;
use SanderMuller\FluentValidation\Rules\PasswordRule;
use SanderMuller\FluentValidation\Rules\StringRule;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\ParsesRulePayloads;
use SanderMuller\FluentValidationRector\RunSummary;
use SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\SimplifyRuleWrappersRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use WeakMap;

/**
 * Rewrites escape-hatch `->rule(...)` calls into native fluent methods on
 * typed FluentRule subclasses. v1 scope: in, notIn, min, max, between,
 * regex, size→exactly. Receiver-type inference walks the chain back to a
 * `FluentRule::{factory}()` static call; intermediate Conditionable hops
 * (when/unless/whenInput) bail because the proxy receiver may not expose
 * the target method.
 *
 * @see SimplifyRuleWrappersRectorTest
 */
final class SimplifyRuleWrappersRector extends AbstractRector implements DocumentedRuleInterface
{
    use LogsSkipReasons;
    use ParsesRulePayloads;

    /**
     * Hard-coded baseline mirroring laravel-fluent-validation v1.17.1's
     * `FluentRule::{factory}()` surface. The reflection overlay in
     * `bootResolutionTables()` extends this with any factories added in
     * later versions; the baseline ensures the rector still works if
     * reflection is unavailable for some reason.
     *
     * @var array<string, class-string>
     */
    private const array FACTORY_BASELINE = [
        'string' => StringRule::class,
        'url' => StringRule::class,
        'uuid' => StringRule::class,
        'ulid' => StringRule::class,
        'ip' => StringRule::class,
        'numeric' => NumericRule::class,
        'integer' => NumericRule::class,
        'array' => ArrayRule::class,
        'file' => FileRule::class,
        'image' => ImageRule::class,
        'date' => DateRule::class,
        'dateTime' => DateRule::class,
        'boolean' => BooleanRule::class,
        'password' => PasswordRule::class,
        'email' => EmailRule::class,
        'field' => FieldRule::class,
        'accepted' => AcceptedRule::class,
    ];

    /**
     * Methods the rector may rewrite to. Bounds the reflection allowlist.
     * Public so `PromoteFieldFactoryRector` can reason about which typed
     * builders are compatible with a given rule-method without duplicating
     * the table.
     */
    public const array V1_REWRITE_TARGETS = [
        'in', 'notIn', 'min', 'max', 'between', 'regex', 'exactly',
        // 1.19.0 additions:
        'enum',
        'positive', 'negative', 'nonNegative', 'nonPositive',
        // COMMA_SEPARATED conditional rules (0.10.1 additions) — array-form
        // rewrite only. Scoped to variadic-compatible fluent signatures:
        // Category A (field + ...values) and Category B (...fields). Rules
        // with overloaded `Closure|bool|string $field` signatures are safe
        // here because the strict-arg whitelist in
        // `buildCommaSeparatedArgsFromArrayItems` rejects shapes that could
        // evaluate to closure/bool/object at runtime.
        'requiredIf', 'requiredUnless', 'excludeIf', 'excludeUnless',
        'prohibitedIf', 'prohibitedUnless',
        'presentIf', 'presentUnless', 'missingIf', 'missingUnless',
        'requiredWith', 'requiredWithAll', 'requiredWithout', 'requiredWithoutAll',
        'presentWith', 'presentWithAll',
        'missingWith', 'missingWithAll',
        'prohibits',
        // ArrayRule-only: requires the receiver to resolve to ArrayRule, gated
        // naturally by the per-class method-availability allowlist.
        'requiredArrayKeys',
    ];

    /**
     * Map Laravel rule-token names to fluent-validation native method names.
     * Most map identity; `size` is renamed to `exactly` per
     * `vendor/sandermuller/laravel-fluent-validation/src/Exceptions/TypedBuilderHint.php:24-25`.
     *
     * Sign helpers (`gt:0` → `positive` family) only fire when the arg is
     * a literal zero — gating happens in the per-family parsing branch
     * because the AST literal is built before the rule-name-to-method
     * resolution runs.
     */
    public const array RULE_NAME_TO_METHOD = [
        'in' => 'in',
        'notIn' => 'notIn',
        'not_in' => 'notIn',
        'min' => 'min',
        'max' => 'max',
        'between' => 'between',
        'regex' => 'regex',
        'size' => 'exactly',
        // 1.19.0 additions:
        'enum' => 'enum',
        'gt' => 'positive',
        'gte' => 'nonNegative',
        'lt' => 'negative',
        'lte' => 'nonPositive',
        // COMMA_SEPARATED conditional rules (0.10.1 additions). Array-form
        // `->rule(['required_if', $field, $value])` → `->requiredIf($field, $value)`.
        // Category A — field + variadic values:
        'required_if' => 'requiredIf',
        'required_unless' => 'requiredUnless',
        'exclude_if' => 'excludeIf',
        'exclude_unless' => 'excludeUnless',
        'prohibited_if' => 'prohibitedIf',
        'prohibited_unless' => 'prohibitedUnless',
        'present_if' => 'presentIf',
        'present_unless' => 'presentUnless',
        'missing_if' => 'missingIf',
        'missing_unless' => 'missingUnless',
        // Category B — pure variadic fields:
        'required_with' => 'requiredWith',
        'required_with_all' => 'requiredWithAll',
        'required_without' => 'requiredWithout',
        'required_without_all' => 'requiredWithoutAll',
        'present_with' => 'presentWith',
        'present_with_all' => 'presentWithAll',
        'missing_with' => 'missingWith',
        'missing_with_all' => 'missingWithAll',
        'prohibits' => 'prohibits',
        // ArrayRule-only; isMethodAvailable() gates receivers that lack it.
        'required_array_keys' => 'requiredArrayKeys',
    ];

    /**
     * Conditionable proxy hops — bail when any chain hop matches.
     * Single-arg `when()`/`unless()` returns `HigherOrderWhenProxy`; we
     * cannot prove the receiver is still the resolved typed-rule subclass.
     */
    private const array CONDITIONABLE_HOPS = ['when', 'unless', 'whenInput'];

    /**
     * Receiver classes where a target method exists natively but its
     * semantics differ from Laravel's like-named rule token. The native
     * method must NOT be considered an equivalent rewrite target.
     *
     * `DateRule::between(from, to)` expands to `after(from)->before(to)`
     * — a chronological range. Laravel's `between:` rule, even on a date
     * field, computes through `getSize()` and falls back to `mb_strlen()`
     * — a size check on the string form. Different semantics; refusing
     * to rewrite preserves user intent.
     *
     * @var array<string, list<class-string>>
     */
    private const array METHOD_RECEIVER_DENYLIST = [
        'between' => [DateRule::class],
    ];

    /** @var array<string, class-string>|null */
    private static ?array $factoryToClass = null;

    /** @var array<class-string, array<string, true>>|null */
    private static ?array $methodAllowlist = null;

    /** @var WeakMap<MethodCall, true> */
    private WeakMap $processedCalls;

    public function __construct()
    {
        $this->processedCalls = new WeakMap();
        RunSummary::registerShutdownHandler();
        $this->bootResolutionTables();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rewrite ->rule(Rule::X())/->rule("X:args") escape hatches into native typed-rule methods.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
FluentRule::string()->rule(Rule::in(['a','b']));
FluentRule::numeric()->rule('min:3');
FluentRule::string()->rule('size:64');
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
FluentRule::string()->in(['a','b']);
FluentRule::numeric()->min(3);
FluentRule::string()->exactly(64);
CODE_SAMPLE
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof MethodCall) {
            return null;
        }

        if (! $node->name instanceof Identifier || $node->name->toString() !== 'rule') {
            return null;
        }

        if (isset($this->processedCalls[$node])) {
            return null;
        }

        if (count($node->args) !== 1 || ! $node->args[0] instanceof Arg) {
            return null;
        }

        // Receiver-resolve runs first so skip-logs only fire for chains
        // we can prove are FluentRule-rooted. Unrelated codebases that
        // happen to expose a `->rule()` method would otherwise spam the
        // skip log with parse-failure entries that aren't actionable.
        $resolution = $this->resolveReceiverType($node);

        if ($resolution === null) {
            return null;
        }

        if ($resolution === 'conditionable_proxy') {
            $this->logSkipForCall($node, 'receiver type unknown — Conditionable proxy in chain');

            return null;
        }

        $parsed = $this->parseRulePayload($node->args[0]->value);

        if ($parsed === null) {
            // Known FluentRule chain but payload shape isn't in v1 scope
            // (variable string, custom Rule object, builder tail like
            // `Rule::in(...)->where(...)`, concatenation, etc.). Legitimate
            // escape-hatch use — not actionable for consumers — so the skip
            // only surfaces in verbose mode. The payload's AST class + a
            // truncated pretty-print is included so verbose dogfood passes
            // can bucket the log by cause (StaticCall Password::default() vs
            // New_ CustomRule vs MethodCall …->withoutTrashed()) without
            // having to open every flagged file.
            $this->logSkipForCall(
                $node,
                sprintf(
                    'rule payload not statically resolvable to a v1 shape: %s',
                    $this->describeUnparseablePayload($node->args[0]->value),
                ),
                verboseOnly: true,
            );

            return null;
        }

        [$ruleName, $ruleArgs] = $parsed;

        $targetMethod = self::RULE_NAME_TO_METHOD[$ruleName] ?? null;

        if ($targetMethod === null) {
            // Recognised rule shape but rule name isn't one of the v1
            // rewrite targets (e.g. 'required', 'email', 'unique:...').
            // Silent — these are valid escape-hatch usage, not a problem.
            return null;
        }

        if (in_array($resolution['class'], self::METHOD_RECEIVER_DENYLIST[$targetMethod] ?? [], true)) {
            $shortClass = (new ReflectionClass($resolution['class']))->getShortName();

            $this->logSkipForCall($node, sprintf(
                '%s() on %s — semantics differ from Laravel rule (refusing to rewrite)',
                $targetMethod,
                $shortClass,
            ));

            return null;
        }

        if (! $this->isMethodAvailable($resolution['class'], $targetMethod)) {
            $shortClass = (new ReflectionClass($resolution['class']))->getShortName();

            // FieldRule missing typed methods (min/max/regex) is a UX
            // opportunity — users could switch to FluentRule::string() /
            // ::numeric() — but not actionable from the skip log directly.
            // Verbose-only so the default log stays signal-heavy.
            $this->logSkipForCall($node, sprintf('%s() not on %s', $targetMethod, $shortClass), verboseOnly: true);

            return null;
        }

        if ($this->argsContainFloat($ruleArgs)
            && ! $this->methodAcceptsFloat($resolution['class'], $targetMethod)) {
            $shortClass = (new ReflectionClass($resolution['class']))->getShortName();

            $this->logSkipForCall($node, sprintf(
                '%s(int) on %s does not accept float token — leaving as escape hatch',
                $targetMethod,
                $shortClass,
            ));

            return null;
        }

        $this->processedCalls[$node] = true;

        return new MethodCall($node->var, new Identifier($targetMethod), $ruleArgs);
    }

    /** @param  list<Arg>  $args */
    private function argsContainFloat(array $args): bool
    {
        foreach ($args as $arg) {
            if ($arg->value instanceof Float_) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the resolved typed-rule method accepts a `float` arg in its
     * first parameter. `int`-only signatures (e.g. `StringRule::min(int)`)
     * runtime-error on Float_ literals, so the rector must skip those.
     *
     * @param  class-string  $class
     */
    private function methodAcceptsFloat(string $class, string $method): bool
    {
        $params = (new ReflectionMethod($class, $method))->getParameters();

        if ($params === []) {
            return true;
        }

        $type = $params[0]->getType();

        if ($type === null) {
            return true;
        }

        if ($type instanceof ReflectionNamedType) {
            return ! in_array($type->getName(), ['int', 'string'], true);
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if ($member instanceof ReflectionNamedType
                    && in_array($member->getName(), ['float', 'mixed'], true)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Walk `$ruleCall->var` inward to the root StaticCall against
     * FluentRule and resolve the typed-rule subclass.
     *
     * @return array{class: class-string, factoryName: string}|'conditionable_proxy'|null
     */
    private function resolveReceiverType(MethodCall $ruleCall): array|string|null
    {
        $current = $ruleCall->var;

        while ($current instanceof MethodCall) {
            if (! $current->name instanceof Identifier) {
                return null;
            }

            if (in_array($current->name->toString(), self::CONDITIONABLE_HOPS, true)) {
                return 'conditionable_proxy';
            }

            $current = $current->var;
        }

        if (! $current instanceof StaticCall) {
            return null;
        }

        if (! $current->name instanceof Identifier) {
            return null;
        }

        if (! $this->isObjectType($current->class, new ObjectType(FluentRule::class))) {
            return null;
        }

        $factoryName = $current->name->toString();
        $resolved = self::$factoryToClass[$factoryName] ?? null;

        if ($resolved === null) {
            return null;
        }

        return ['class' => $resolved, 'factoryName' => $factoryName];
    }

    /**
     * Whether the resolved typed-rule subclass exposes a v1 rewrite-target
     * method. Per-class allowlist is built from reflection over
     * `V1_REWRITE_TARGETS` at bootstrap, so newly added native methods are
     * picked up without code changes once they fall in scope.
     *
     * @param  class-string  $class
     */
    private function isMethodAvailable(string $class, string $method): bool
    {
        return isset(self::$methodAllowlist[$class][$method]);
    }

    /**
     * Resolve the enclosing class via PHPStan scope (Rector populates the
     * SCOPE attribute reliably; the parent-node attribute is not). Falls
     * back to `top-level` for nodes outside any class — rare in practice
     * but possible in plain scripts.
     */
    /**
     * Describe an unparseable `->rule(...)` payload for the verbose skip log.
     * Emits the AST node's short class name followed by a single-line,
     * whitespace-collapsed, 60-char-truncated pretty-print so consumers can
     * bucket the 57-entry "not resolvable" category by cause without opening
     * each flagged file. Added 0.12.0 as the sequencing gate for FieldRule
     * auto-upgrade + payload-specific follow-on converters (peer `e0cp6lq3`
     * 0.11.0 dogfood, 2026-04-24).
     */
    private function describeUnparseablePayload(Expr $payload): string
    {
        $shortClass = (new ReflectionClass($payload))->getShortName();

        $rendered = (new Standard())->prettyPrintExpr($payload);
        $rendered = preg_replace('/\s+/', ' ', $rendered) ?? $rendered;
        $rendered = trim($rendered);

        $maxLength = 60;

        if (strlen($rendered) > $maxLength) {
            $rendered = substr($rendered, 0, $maxLength - 3) . '...';
        }

        return sprintf('%s %s', $shortClass, $rendered);
    }

    private function logSkipForCall(MethodCall $node, string $reason, bool $verboseOnly = false): void
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);
        $className = 'top-level';

        if ($scope instanceof Scope && $scope->getClassReflection() instanceof ClassReflection) {
            $className = $scope->getClassReflection()->getName();
        }

        $this->logSkipByName($className, $reason, $verboseOnly);
    }

    /**
     * Build the factory→class map and per-class method allowlist. Hard-coded
     * baseline from `FACTORY_BASELINE` is augmented by reflection over
     * `FluentRule`'s public statics so newly added factories self-register;
     * the per-class allowlist intersects each class's public methods against
     * `V1_REWRITE_TARGETS`.
     */
    private function bootResolutionTables(): void
    {
        if (self::$factoryToClass !== null && self::$methodAllowlist !== null) {
            return;
        }

        $factoryMap = self::FACTORY_BASELINE;

        $reflection = new ReflectionClass(FluentRule::class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC) as $method) {
            $returnType = $method->getReturnType();

            if (! $returnType instanceof ReflectionNamedType) {
                continue;
            }

            $returnClass = $returnType->getName();
            // Only register factories whose return implements FluentRuleContract
            // — excludes `anyOf` (returns AnyOf) and any future helper that
            // doesn't produce a typed-rule receiver. AnyOf isn't covered by v1
            // rewrites; receiver-type inference treats it as unknown.
            if (! class_exists($returnClass)) {
                continue;
            }

            if (! is_a($returnClass, FluentRuleContract::class, true)) {
                continue;
            }

            $factoryMap[$method->getName()] = $returnClass;
        }

        self::$factoryToClass = $factoryMap;

        $allowlist = [];

        foreach (array_unique(array_values($factoryMap)) as $class) {
            $allowlist[$class] = [];
            $classRefl = new ReflectionClass($class);

            foreach (self::V1_REWRITE_TARGETS as $target) {
                if ($classRefl->hasMethod($target) && $classRefl->getMethod($target)->isPublic()) {
                    $allowlist[$class][$target] = true;
                }
            }
        }

        self::$methodAllowlist = $allowlist;
    }
}
