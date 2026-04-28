<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\AcceptedRule;
use SanderMuller\FluentValidation\Rules\ArrayRule;
use SanderMuller\FluentValidation\Rules\BooleanRule;
use SanderMuller\FluentValidation\Rules\Concerns\HasEmbeddedRules;
use SanderMuller\FluentValidation\Rules\Concerns\HasFieldModifiers;
use SanderMuller\FluentValidation\Rules\DateRule;
use SanderMuller\FluentValidation\Rules\DeclinedRule;
use SanderMuller\FluentValidation\Rules\EmailRule;
use SanderMuller\FluentValidation\Rules\FieldRule;
use SanderMuller\FluentValidation\Rules\FileRule;
use SanderMuller\FluentValidation\Rules\ImageRule;
use SanderMuller\FluentValidation\Rules\NumericRule;
use SanderMuller\FluentValidation\Rules\PasswordRule;
use SanderMuller\FluentValidation\Rules\StringRule;

/**
 * Builds the reflection-derived allowlist of `FluentRule::*` factories and
 * typed-rule methods that accept a `?string $message = null` parameter.
 * Extracted from `InlineMessageParamRector` to keep the rector class's
 * cognitive complexity under PHPStan's ceiling — the rector delegates
 * surface discovery to this helper via a single `load()` call.
 *
 * The allowlist + factory-to-class map are static (process-lifetime cache);
 * pest's parallel runner isolates at the PHP-process level, so no
 * cross-worker contention.
 *
 * @internal
 */
final class InlineMessageSurface
{
    /**
     * Typed-rule classes whose public methods participate in the allowlist.
     * 12 classes per the 1.20.0 surface.
     *
     * @var list<class-string>
     */
    public const array TYPED_RULE_CLASSES = [
        AcceptedRule::class,
        ArrayRule::class,
        BooleanRule::class,
        DateRule::class,
        DeclinedRule::class,
        EmailRule::class,
        FieldRule::class,
        FileRule::class,
        ImageRule::class,
        NumericRule::class,
        PasswordRule::class,
        StringRule::class,
    ];

    /** @var array<class-string, list<string>> */
    public const array COMPOSITE_METHODS = [
        NumericRule::class => ['digits', 'digitsBetween', 'exactly'],
        DateRule::class => ['between', 'betweenOrEqual'],
        ImageRule::class => [
            'width', 'height', 'minWidth', 'maxWidth',
            'minHeight', 'maxHeight', 'ratio', 'dimensions',
        ],
    ];

    /** @var array<class-string, list<string>> */
    public const array MODE_MODIFIERS = [
        EmailRule::class => [
            'rfcCompliant', 'strict', 'validateMxRecord',
            'preventSpoofing', 'withNativeValidation',
        ],
        PasswordRule::class => [
            'min', 'max', 'letters', 'mixedCase',
            'numbers', 'symbols', 'uncompromised',
        ],
    ];

    /** @var list<string> */
    public const array FACTORIES_WITHOUT_MESSAGE_PARAM = [
        'date', 'dateTime', 'field', 'anyOf', 'password',
    ];

    /**
     * Override table for methods whose emitted rule-token differs from the
     * snake_case of the method name. Keyed by `Class::method`. Source: peer
     * handoff 2026-04-22 (§Phase 3 emitted_key derivation).
     *
     * @var array<string, string>
     */
    public const array EMITTED_KEY_OVERRIDES = [
        NumericRule::class . '::exactly' => 'size',
        NumericRule::class . '::greaterThan' => 'gt',
        NumericRule::class . '::greaterThanOrEqualTo' => 'gte',
        NumericRule::class . '::lessThan' => 'lt',
        NumericRule::class . '::lessThanOrEqualTo' => 'lte',
        NumericRule::class . '::positive' => 'gt',
        NumericRule::class . '::negative' => 'lt',
        NumericRule::class . '::nonNegative' => 'gte',
        NumericRule::class . '::nonPositive' => 'lte',
        StringRule::class . '::exactly' => 'size',
        ArrayRule::class . '::exactly' => 'size',
        FileRule::class . '::exactly' => 'size',
        StringRule::class . '::alphaNumeric' => 'alpha_num',
        DateRule::class . '::beforeToday' => 'before',
        DateRule::class . '::afterToday' => 'after',
        DateRule::class . '::todayOrBefore' => 'before_or_equal',
        DateRule::class . '::todayOrAfter' => 'after_or_equal',
        DateRule::class . '::past' => 'before',
        DateRule::class . '::future' => 'after',
        DateRule::class . '::nowOrPast' => 'before_or_equal',
        DateRule::class . '::nowOrFuture' => 'after_or_equal',
        NumericRule::class . '::digits' => 'digits',
        NumericRule::class . '::digitsBetween' => 'digits_between',
        DateRule::class . '::between' => 'before',
        DateRule::class . '::betweenOrEqual' => 'before_or_equal',
    ];

    /** @var array<string, array{category: string, is_variadic: bool, emitted_key: string}>|null */
    private static ?array $allowlist = null;

    /** @var array<string, class-string>|null */
    private static ?array $factoryToClass = null;

    private static ?bool $surfaceAvailable = null;

    /**
     * Populate + return the allowlist. Idempotent — boots on first call,
     * short-circuits thereafter.
     *
     * @return array<string, array{category: string, is_variadic: bool, emitted_key: string}>
     */
    public static function load(): array
    {
        if (self::$allowlist !== null) {
            return self::$allowlist;
        }

        self::$surfaceAvailable = self::detectSurfaceAvailability();

        if (self::$surfaceAvailable === false) {
            self::$allowlist = [];

            return [];
        }

        $allowlist = [];

        self::collectFactoryAllowlist($allowlist);

        // Filter to rule classes that exist under the installed
        // sister-package version. Mirrors the class_exists guard pattern
        // applied across rector class-typed const tables (cross-rector
        // correctness invariant: any iteration that reflects on a
        // hardcoded rule-class FQCN must pre-filter for class_exists).
        // Without this, BASELINE additions that race ahead of the
        // composer constraint bump fatal at boot for consumers on
        // older sister-package versions. Companion fixture:
        // tests/RectorInternalContractsTest::testEveryHardcodedClassTableResolvesCleanly.
        foreach (array_filter(self::TYPED_RULE_CLASSES, class_exists(...)) as $class) {
            self::collectTypedRuleAllowlist($class, $allowlist);
        }

        ksort($allowlist);

        self::$allowlist = $allowlist;

        return $allowlist;
    }

    public static function isSurfaceAvailable(): bool
    {
        self::load();

        return self::$surfaceAvailable ?? false;
    }

    /** @return array<string, class-string> */
    public static function factoryToClass(): array
    {
        self::load();

        return self::$factoryToClass ?? [];
    }

    /**
     * @param  array<string, array{category: string, is_variadic: bool, emitted_key: string}>  $allowlist
     */
    private static function collectFactoryAllowlist(array &$allowlist): void
    {
        $factoryReflection = new ReflectionClass(FluentRule::class);

        foreach ($factoryReflection->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if (str_starts_with($name, '__')) {
                continue;
            }

            $allowlist['FluentRule::' . $name] = [
                'category' => self::categorizeFactory($name, $method),
                'is_variadic' => self::methodIsVariadicTrailing($method),
                'emitted_key' => '',
            ];

            self::recordFactoryReturnType($method);
        }
    }

    private static function categorizeFactory(string $name, ReflectionMethod $method): string
    {
        if (in_array($name, self::FACTORIES_WITHOUT_MESSAGE_PARAM, true)) {
            return 'factory_no_message';
        }

        return self::methodHasMessageParam($method) ? 'factory_rewritable' : 'factory_no_message';
    }

    /**
     * @param  class-string  $class
     * @param  array<string, array{category: string, is_variadic: bool, emitted_key: string}>  $allowlist
     */
    private static function collectTypedRuleAllowlist(string $class, array &$allowlist): void
    {
        $classReflection = new ReflectionClass($class);

        foreach ($classReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! self::isRewritableMethodCandidate($method, $class)) {
                continue;
            }

            $key = $class . '::' . $method->getName();

            if (isset($allowlist[$key])) {
                continue;
            }

            $allowlist[$key] = [
                'category' => self::categorizeMethod($class, $method->getName()),
                'is_variadic' => self::methodIsVariadicTrailing($method),
                'emitted_key' => self::deriveEmittedKey($class, $method->getName()),
            ];
        }
    }

    /** @param  class-string  $class */
    private static function isRewritableMethodCandidate(ReflectionMethod $method, string $class): bool
    {
        if ($method->isStatic() || $method->isConstructor() || $method->isAbstract()) {
            return false;
        }

        if (str_starts_with($method->getName(), '__')) {
            return false;
        }

        $declaringClass = $method->getDeclaringClass()->getName();

        if (! in_array($declaringClass, [$class, HasFieldModifiers::class, HasEmbeddedRules::class], true)) {
            return false;
        }

        return self::methodHasMessageParam($method);
    }

    private static function recordFactoryReturnType(ReflectionMethod $method): void
    {
        $returnType = $method->getReturnType();

        if (! $returnType instanceof ReflectionNamedType) {
            return;
        }

        $returnClass = $returnType->getName();

        if (! class_exists($returnClass)) {
            return;
        }

        if (! in_array($returnClass, self::TYPED_RULE_CLASSES, true)) {
            return;
        }

        self::$factoryToClass ??= [];
        self::$factoryToClass[$method->getName()] = $returnClass;
    }

    /** @param  class-string  $class */
    private static function deriveEmittedKey(string $class, string $method): string
    {
        $overrideKey = $class . '::' . $method;

        return self::EMITTED_KEY_OVERRIDES[$overrideKey] ?? self::camelToSnakeCase($method);
    }

    private static function camelToSnakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name));
    }

    /** @param  class-string  $class */
    private static function categorizeMethod(string $class, string $name): string
    {
        if (in_array($name, self::COMPOSITE_METHODS[$class] ?? [], true)) {
            return 'composite';
        }

        if (in_array($name, self::MODE_MODIFIERS[$class] ?? [], true)) {
            return 'mode_modifier';
        }

        return 'rewritable';
    }

    private static function detectSurfaceAvailability(): bool
    {
        return self::methodHasMessageParam(new ReflectionMethod(FluentRule::class, 'email'));
    }

    private static function methodHasMessageParam(ReflectionMethod $method): bool
    {
        foreach ($method->getParameters() as $param) {
            if ($param->getName() !== 'message') {
                continue;
            }

            $type = $param->getType();

            if (! $type instanceof ReflectionNamedType) {
                continue;
            }

            if ($type->getName() !== 'string') {
                continue;
            }

            if (! $type->allowsNull()) {
                continue;
            }

            if (! $param->isDefaultValueAvailable()) {
                continue;
            }

            if ($param->getDefaultValue() !== null) {
                continue;
            }

            return true;
        }

        return false;
    }

    private static function methodIsVariadicTrailing(ReflectionMethod $method): bool
    {
        foreach ($method->getParameters() as $param) {
            if ($param->isVariadic()) {
                return true;
            }
        }

        return false;
    }
}
