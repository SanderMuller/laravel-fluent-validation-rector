<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Internal\RunSummary;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Collapses `->message('...')` / `->messageFor('key', '...')` chain calls
 * into the inline `message:` named parameter on FluentRule factories and
 * rule methods, against the laravel-fluent-validation ^1.20 surface.
 *
 * Opt-in SIMPLIFY set; ergonomic simplification, not a correctness fix.
 * Consumers on older floors receive no rewrites — the bootstrap guard in
 * `InlineMessageSurface` detects the missing `message:` param on
 * `FluentRule::email` and returns an empty allowlist, so `refactor()`
 * short-circuits on every call.
 *
 * Surface discovery + categorization live in `InlineMessageSurface` to
 * keep this class's cognitive complexity under the PHPStan ceiling. The
 * rector holds only the rewrite predicates + skip-log templates.
 */
final class InlineMessageParamRector extends AbstractRector implements DocumentedRuleInterface
{
    use LogsSkipReasons;

    /**
     * Rule-object class-basename to emitted-key mapping for Phase 4's
     * `->rule(new X())->messageFor(K, ...)` collapse. Mirrors the explicit
     * `match` cases in `HasFieldModifiers::addRule()` (vendor source at
     * `src/Rules/Concerns/HasFieldModifiers.php:122-136`). Classes not
     * listed fall back to `lcfirst(class_basename)` at runtime.
     *
     * @var array<string, string>
     */
    public const array RULE_OBJECT_KEY_OVERRIDES = [
        'RequiredIf' => 'required',
        'RequiredUnless' => 'required',
        'ProhibitedIf' => 'prohibited',
        'ProhibitedUnless' => 'prohibited',
        'ExcludeIf' => 'exclude',
        'ExcludeUnless' => 'exclude',
        'In' => 'in',
        'NotIn' => 'not_in',
        'Unique' => 'unique',
        'Exists' => 'exists',
    ];

    /**
     * Verbatim skip-log template for `->rule(new Password())
     * ->messageFor('password', ...)`. Matches the verbatim text resolved
     * in spec §1.4 (peer Q4 handoff, tightened 2026-04-22). Consumers hit
     * this when they're on Laravel 11 where the `'password.password'`
     * key never resolves — the message silently no-ops pre-L12.
     */
    public const string PASSWORD_L11_L12_SKIP_TEMPLATE =
        "Skipping messageFor('password', ...) on PasswordRule: key resolves via Laravel's "
        . 'getFromLocalArray shortRule lookup (class-basename → \'password.password\'), which is L12+ only. '
        . "L11's 3-key lookup (attribute.rule, rule, attribute) does not match. "
        . 'Leaving the chain unchanged preserves the working-on-L12 / silently-broken-on-L11 state. '
        . "Consumers targeting L11 should use messageFor('password.letters', ...), 'password.mixed', etc. "
        . 'sub-keys or a messages(): array entry.';

    public function __construct()
    {
        RunSummary::registerShutdownHandler();
        InlineMessageSurface::load();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Collapse ->message()/->messageFor() chain calls into inline `message:` named param on FluentRule factories and rule methods.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
FluentRule::email()->message('Bad email.')->required();
FluentRule::string()->min(3)->messageFor('min', 'Too short.');
FluentRule::string()->rule(new MyCustomRule())->messageFor('myCustomRule', 'msg');
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
FluentRule::email(message: 'Bad email.')->required();
FluentRule::string()->min(3, message: 'Too short.');
FluentRule::string()->rule(new MyCustomRule(), message: 'msg');
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
        if (! InlineMessageSurface::isSurfaceAvailable()) {
            return null;
        }

        if (! $node instanceof MethodCall) {
            return null;
        }

        if (! $node->name instanceof Identifier) {
            return null;
        }

        $methodName = $node->name->toString();

        if ($methodName === 'messageFor') {
            return $this->refactorMessageFor($node);
        }

        if ($methodName !== 'message') {
            return null;
        }

        return $this->refactorMessage($node);
    }

    /**
     * Phase 2 predicate: `->message()` must be directly on a
     * `FluentRule::$factory()` StaticCall — no intervening MethodCall
     * (rule method or Conditionable hop) allowed. Any intermediate
     * MethodCall breaks the direct-receiver relationship and naturally
     * rejects both cases with one structural check.
     */
    private function refactorMessage(MethodCall $node): ?Node
    {
        if (! $node->var instanceof StaticCall) {
            return null;
        }

        $staticCall = $node->var;

        if (! $staticCall->name instanceof Identifier) {
            return null;
        }

        if (! $this->isObjectType($staticCall->class, new ObjectType(FluentRule::class))) {
            return null;
        }

        if (count($node->args) !== 1 || ! $node->args[0] instanceof Arg) {
            return null;
        }

        $messageArg = $node->args[0];

        if ($messageArg->name instanceof Identifier) {
            return null;
        }

        if (! $this->isLiteralStringExpression($messageArg->value)) {
            return null;
        }

        $factoryName = $staticCall->name->toString();
        $allowlist = InlineMessageSurface::load();
        $entry = $allowlist['FluentRule::' . $factoryName] ?? null;

        if ($entry === null) {
            return null;
        }

        if ($entry['category'] === 'factory_no_message') {
            $this->logSkipForCall($node, sprintf(
                '->message() collapse on FluentRule::%s(): factory has no message: param — %s. Chain left unchanged.',
                $factoryName,
                $this->noMessageReason($factoryName),
            ));

            return null;
        }

        if ($entry['category'] !== 'factory_rewritable') {
            return null;
        }

        return $this->rebuildFactoryWithMessage($staticCall, $messageArg->value);
    }

    /**
     * Phase 3 predicate: `->messageFor(K, msg)` directly on a rule-method
     * `MethodCall(name=$method)` where `(receiverClass::$method, emitted_key=K)`
     * is in the allowlist as `rewritable` and not variadic.
     */
    private function refactorMessageFor(MethodCall $node): ?Node
    {
        if (count($node->args) !== 2) {
            return null;
        }

        $keyArg = $node->args[0];
        $messageArg = $node->args[1];

        if (! $keyArg instanceof Arg || ! $messageArg instanceof Arg) {
            return null;
        }

        if ($keyArg->name instanceof Identifier || $messageArg->name instanceof Identifier) {
            return null;
        }

        if (! $keyArg->value instanceof String_) {
            return null;
        }

        $messageForKey = $keyArg->value->value;

        if (! $this->isLiteralStringExpression($messageArg->value)) {
            return null;
        }

        if ($node->var instanceof StaticCall) {
            return $this->rewriteMessageForOnFactory($node, $node->var, $messageForKey, $messageArg->value);
        }

        if (! $node->var instanceof MethodCall) {
            return null;
        }

        $receiverCall = $node->var;

        if (! $receiverCall->name instanceof Identifier) {
            return null;
        }

        $receiverMethod = $receiverCall->name->toString();

        if ($receiverMethod === 'rule') {
            return $this->refactorMessageForOnRuleObject($node, $receiverCall, $messageForKey, $messageArg->value);
        }

        $receiverClass = $this->resolveReceiverClass($receiverCall);

        if ($receiverClass === null) {
            return null;
        }

        $entry = InlineMessageSurface::load()[$receiverClass . '::' . $receiverMethod] ?? null;

        if ($entry === null) {
            return null;
        }

        return $this->applyMessageForEntry($node, $receiverCall, $receiverClass, $receiverMethod, $messageForKey, $messageArg->value, $entry);
    }

    /**
     * `FluentRule::email()->messageFor('email', 'msg')` shape — collapse to
     * factory-level inline `message:` when the key matches the factory name.
     */
    private function rewriteMessageForOnFactory(MethodCall $messageForCall, StaticCall $factory, string $key, Expr $message): ?Node
    {
        if (! $factory->name instanceof Identifier) {
            return null;
        }

        if (! $this->isObjectType($factory->class, new ObjectType(FluentRule::class))) {
            return null;
        }

        $factoryName = $factory->name->toString();

        if ($factoryName !== $key) {
            return null;
        }

        $entry = InlineMessageSurface::load()['FluentRule::' . $factoryName] ?? null;

        if ($entry === null) {
            return null;
        }

        if ($entry['category'] === 'factory_no_message') {
            $this->logSkipForCall($messageForCall, sprintf(
                "->messageFor('%s', ...) collapse on FluentRule::%s(): factory has no message: param — %s. Chain left unchanged.",
                $key,
                $factoryName,
                $this->noMessageReason($factoryName),
            ));

            return null;
        }

        if ($entry['category'] !== 'factory_rewritable') {
            return null;
        }

        return $this->rebuildFactoryWithMessage($factory, $message);
    }

    /**
     * @param  class-string  $receiverClass
     * @param  array{category: string, is_variadic: bool, emitted_key: string}  $entry
     */
    private function applyMessageForEntry(
        MethodCall $messageForCall,
        MethodCall $receiverCall,
        string $receiverClass,
        string $receiverMethod,
        string $messageForKey,
        Expr $message,
        array $entry,
    ): ?Node {
        $skipReason = $this->messageForSkipReason($receiverClass, $receiverMethod, $messageForKey, $entry);

        if ($skipReason !== null) {
            $this->logSkipForCall($messageForCall, $skipReason);

            return null;
        }

        if ($entry['category'] !== 'rewritable') {
            return null;
        }

        if ($messageForKey !== $entry['emitted_key']) {
            // Pre-existing user misbinding — no rewrite, no skip-log
            // (per spec §3 non-goal).
            return null;
        }

        return $this->rebuildReceiverWithMessage($receiverCall, $message);
    }

    /**
     * Resolve the skip-log reason for a messageFor receiver, or null if
     * the entry is rewritable. Splits skip-category classification out of
     * `applyMessageForEntry` so the main predicate reads linearly.
     *
     * @param  class-string  $receiverClass
     * @param  array{category: string, is_variadic: bool, emitted_key: string}  $entry
     */
    private function messageForSkipReason(string $receiverClass, string $receiverMethod, string $messageForKey, array $entry): ?string
    {
        if ($entry['is_variadic']) {
            return sprintf(
                "->messageFor('%s', ...) collapse on %s::%s(): variadic-trailing method — message: position ambiguous with trailing ...args. Chain left unchanged.",
                $messageForKey,
                $this->shortClass($receiverClass),
                $receiverMethod,
            );
        }

        if ($entry['category'] === 'composite') {
            return sprintf(
                "->messageFor('%s', ...) collapse on %s::%s(): composite method emits multiple sub-keys; inline message: binds to '%s' (last sub-rule), not '%s'. Chain left unchanged.",
                $messageForKey,
                $this->shortClass($receiverClass),
                $receiverMethod,
                $entry['emitted_key'],
                $messageForKey,
            );
        }

        if ($entry['category'] === 'mode_modifier') {
            return sprintf(
                "->messageFor('%s', ...) collapse on %s::%s(): mode-modifier method does not call addRule() — no message target. Chain left unchanged.",
                $messageForKey,
                $this->shortClass($receiverClass),
                $receiverMethod,
            );
        }

        return null;
    }

    /**
     * Phase 4 predicate: `->rule(new $RuleClass(...))->messageFor($K, $msg)`.
     * Collapses to `->rule(new $RuleClass(...), message: $msg)` when the
     * rule object's class-basename-derived key matches `$K`. Password
     * object gets the verbatim L11/L12 skip-log instead of a rewrite.
     */
    private function refactorMessageForOnRuleObject(MethodCall $messageForCall, MethodCall $ruleCall, string $messageForKey, Expr $message): ?Node
    {
        if ($ruleCall->args === [] || ! $ruleCall->args[0] instanceof Arg) {
            return null;
        }

        $firstArg = $ruleCall->args[0];

        if (! $firstArg->value instanceof New_) {
            return null;
        }

        $newExpr = $firstArg->value;

        if (! $newExpr->class instanceof Name) {
            return null;
        }

        $shortClass = $newExpr->class->getLast();

        if ($shortClass === 'Password' && $messageForKey === 'password') {
            $this->logSkipForCall($messageForCall, self::PASSWORD_L11_L12_SKIP_TEMPLATE);

            return null;
        }

        $emittedKey = self::RULE_OBJECT_KEY_OVERRIDES[$shortClass] ?? lcfirst($shortClass);

        if ($messageForKey !== $emittedKey) {
            return null;
        }

        return $this->rebuildReceiverWithMessage($ruleCall, $message);
    }

    /**
     * Rebuild the receiver MethodCall with an appended `message:` named arg.
     */
    private function rebuildReceiverWithMessage(MethodCall $receiver, Expr $message): MethodCall
    {
        $newArgs = [];

        foreach ($receiver->args as $existing) {
            if ($existing instanceof Arg) {
                $newArgs[] = $existing;
            }
        }

        $newArgs[] = new Arg($message, name: new Identifier('message'));

        return new MethodCall($receiver->var, $receiver->name, $newArgs);
    }

    /**
     * Walk back from a rule-method MethodCall to the root `FluentRule::$factory`
     * StaticCall and resolve the typed-rule class via the factory map.
     * Conditionable hops are walked through — they don't affect messageFor
     * semantics (messageFor writes to `customMessages[$key]`, not
     * `$lastConstraint`).
     *
     * @return class-string|null
     */
    private function resolveReceiverClass(MethodCall $call): ?string
    {
        $current = $call->var;

        while ($current instanceof MethodCall) {
            if (! $current->name instanceof Identifier) {
                return null;
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

        return InlineMessageSurface::factoryToClass()[$factoryName] ?? null;
    }

    /** @param  class-string  $class */
    private function shortClass(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    /**
     * Rebuild the factory StaticCall with an appended `message:` named arg.
     */
    private function rebuildFactoryWithMessage(StaticCall $factory, Expr $message): StaticCall
    {
        $newArgs = [];

        foreach ($factory->args as $existing) {
            if ($existing instanceof Arg) {
                $newArgs[] = $existing;
            }
        }

        $newArgs[] = new Arg($message, name: new Identifier('message'));

        return new StaticCall($factory->class, $factory->name, $newArgs);
    }

    private function isLiteralStringExpression(Expr $expr): bool
    {
        if ($expr instanceof String_) {
            return true;
        }

        if ($expr instanceof Concat) {
            return $this->isLiteralStringExpression($expr->left)
                && $this->isLiteralStringExpression($expr->right);
        }

        return false;
    }

    private function noMessageReason(string $factoryName): string
    {
        return match ($factoryName) {
            'date', 'dateTime' => 'emitted key varies at build (date vs date_format:...)',
            'password' => 'L11/L12-divergent password sub-key lookup',
            'field', 'anyOf' => 'no implicit constraint',
            default => 'not in rewritable allowlist',
        };
    }

    private function logSkipForCall(MethodCall $node, string $reason): void
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);
        $className = 'top-level';

        if ($scope instanceof Scope && $scope->getClassReflection() instanceof ClassReflection) {
            $className = $scope->getClassReflection()->getName();
        }

        $this->logSkipByName($className, $reason);
    }
}
