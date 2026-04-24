<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\AcceptedRule;
use SanderMuller\FluentValidation\Rules\ArrayRule;
use SanderMuller\FluentValidation\Rules\BooleanRule;
use SanderMuller\FluentValidation\Rules\DateRule;
use SanderMuller\FluentValidation\Rules\EmailRule;
use SanderMuller\FluentValidation\Rules\FileRule;
use SanderMuller\FluentValidation\Rules\ImageRule;
use SanderMuller\FluentValidation\Rules\NumericRule;
use SanderMuller\FluentValidation\Rules\PasswordRule;
use SanderMuller\FluentValidation\Rules\StringRule;
use SanderMuller\FluentValidationRector\Rector\Concerns\ParsesRulePayloads;
use SanderMuller\FluentValidationRector\Rector\Concerns\PromotesPasswordEmailFactory;
use SanderMuller\FluentValidationRector\RunSummary;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Promote `FluentRule::field()` to a typed factory (`::string()`, `::numeric()`,
 * etc.) when every `->rule(...)` wrapper in the chain parses to a v1-scope rule
 * whose target method lives on exactly one typed FluentRule subclass.
 *
 * Rationale: `FluentRule::field()->rule('max:61')` currently skip-logs
 * "`max()` not on FieldRule" in `SimplifyRuleWrappersRector`. The user's
 * intent — a max-length check on a string — is clear, but `FieldRule` has no
 * `max()` method. Promoting the factory to `FluentRule::string()` lets the
 * next `SimplifyRuleWrappersRector` pass rewrite `->rule('max:61')` to
 * `->max(61)` naturally.
 *
 * **Runs before** `SimplifyRuleWrappersRector` in the `SIMPLIFY` set so the
 * second rector's next pass picks up the promoted factory.
 *
 * **Semantic note**: StringRule implicitly adds Laravel's `string` rule to
 * the validator output, and NumericRule adds `numeric`. `FieldRule` adds
 * neither. Promoting `FluentRule::field()->rule('max:61')` to
 * `FluentRule::string()->max(61)` therefore changes validation behaviour for
 * non-string inputs: Laravel will now fail early with "field must be a
 * string" instead of evaluating `max` against the value. In the v1 rewrite
 * scope this matches intent in the overwhelming majority of cases — the
 * user reaching for `max(N)` on a field almost always means character-count
 * on a string. Edge cases where the user specifically wanted the untyped
 * `FieldRule` surface should be caught in the rector diff review.
 *
 * Bails silently when:
 * - Not all `->rule(...)` wrappers in the chain parse to v1-scope rules.
 * - The parsed rules' compatible-builder intersection is not a singleton
 *   (e.g. the chain mixes `max:...` which exists on string/numeric/array/file
 *   with a rule specific to one of them — the intersection may be > 1).
 * - The single compatible class is `FieldRule` itself (no promotion needed).
 * - At least one `->rule()` call in the chain failed to constrain (would
 *   mean zero signal about target type).
 *
 * @see PromoteFieldFactoryRectorTest
 */
final class PromoteFieldFactoryRector extends AbstractRector implements DocumentedRuleInterface
{
    use ParsesRulePayloads;
    use PromotesPasswordEmailFactory;

    /**
     * Conditionable hops that turn the chain receiver into a
     * `HigherOrderWhenProxy`. `SimplifyRuleWrappersRector` bails on these
     * because the proxy doesn't expose the target method; we mirror the
     * check so we don't promote the factory in isolation. Without this,
     * `FluentRule::field()->when($c, $fn)->rule('max:61')` would become
     * `FluentRule::string()->when($c, $fn)->rule('max:61')` — the escape
     * hatch stays (no `->rule()` lowering because of the proxy) but the
     * factory promotion adds Laravel's implicit `string` rule to the
     * validator output. Net: behavior change the user didn't ask for.
     *
     * @var list<string>
     */
    private const array CONDITIONABLE_HOPS = ['when', 'unless', 'whenInput'];

    /**
     * Factory method name → concrete FluentRule subclass. Hardcoded reverse
     * map so this rector doesn't depend on `SimplifyRuleWrappersRector`'s
     * reflection-built `$factoryToClass` table (private, cached after boot).
     * Order matters only for deterministic iteration; intersection logic
     * is symmetric.
     *
     * @var array<class-string, string>
     */
    private const array TYPED_BUILDER_TO_FACTORY = [
        StringRule::class => 'string',
        NumericRule::class => 'numeric',
        ArrayRule::class => 'array',
        FileRule::class => 'file',
        ImageRule::class => 'image',
        DateRule::class => 'date',
        BooleanRule::class => 'boolean',
        EmailRule::class => 'email',
        PasswordRule::class => 'password',
        AcceptedRule::class => 'accepted',
    ];

    /**
     * Target factories whose first positional parameter matches
     * `FluentRule::field(?string $label = null)`'s signature, so rewriting
     * `field('Meta')` → `<target>('Meta')` preserves the argument binding.
     *
     * Excluded here on purpose: `array(Arrayable|array|null $keys = null, ...)`
     * binds the first arg to `$keys`, and `password(?int $min = null, ...)`
     * binds it to `$min` (int). Rewriting `field('Meta')` to either would
     * silently mis-bind the label — `array` loses the label entirely and
     * treats it as a key-list; `password` TypeErrors on `string` vs `int`.
     * When the source `field()` call has no args, every target in
     * `TYPED_BUILDER_TO_FACTORY` is safe; when it has args we only promote
     * into this allowlist.
     *
     * @var list<class-string>
     */
    private const array LABEL_FIRST_TARGETS = [
        StringRule::class,
        NumericRule::class,
        FileRule::class,
        ImageRule::class,
        DateRule::class,
        BooleanRule::class,
        EmailRule::class,
        AcceptedRule::class,
    ];

    public function __construct()
    {
        RunSummary::registerShutdownHandler();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Promote FluentRule::field() to a typed factory when all ->rule(...) wrappers in the chain target methods on exactly one typed builder.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
FluentRule::field()
    ->rule('max:61')
    ->rule('regex:/^[a-z]+$/');
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
FluentRule::string()
    ->rule('max:61')
    ->rule('regex:/^[a-z]+$/');
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

        // Walk `->var` down to the chain root. Every MethodCall in a
        // `FluentRule::<factory>()->…->…` chain will fire this rector — the
        // factory-name check on the resolved root lets non-first hops exit
        // immediately (post-promotion the root's name has changed, so only
        // the first fire matches the source factory). This parent-first
        // traversal ordering is what makes the splice+rename safe: outer
        // MethodCall fires first, mutates the chain, subsequent inner hops
        // see the promoted state and bail.
        $root = $this->walkToStaticCallRoot($node);

        if (! $root instanceof StaticCall) {
            return null;
        }

        if (! $root->class instanceof Name) {
            return null;
        }

        if ($this->getName($root->class) !== FluentRule::class) {
            return null;
        }

        if (! $root->name instanceof Identifier) {
            return null;
        }

        $rootFactoryName = $root->name->toString();

        if ($rootFactoryName === 'field') {
            return $this->applyFieldTrigger($root, $node);
        }

        if ($rootFactoryName === 'string') {
            return $this->applyPasswordEmailTrigger($root, $node, self::CONDITIONABLE_HOPS);
        }

        return null;
    }

    /**
     * Trigger A (original): `FluentRule::field()->rule('max:61')->rule(...)`
     * → `FluentRule::string()` / `::numeric()` / etc. when all `->rule(...)`
     * payloads resolve to methods on exactly one typed builder.
     */
    private function applyFieldTrigger(StaticCall $root, MethodCall $node): ?Node
    {
        $ruleCalls = $this->collectRuleCallsFromRoot($root, $node);

        if ($ruleCalls === []) {
            return null;
        }

        $compatIntersection = $this->intersectCompatibleBuilders($ruleCalls);

        if ($compatIntersection === null) {
            return null;
        }

        if (count($compatIntersection) !== 1) {
            return null;
        }

        $targetClass = $compatIntersection[0];
        $factoryName = self::TYPED_BUILDER_TO_FACTORY[$targetClass] ?? null;

        if ($factoryName === null) {
            return null;
        }

        // Arg-binding safety: FluentRule::field(?string $label) vs. targets
        // like FluentRule::array(?array $keys, ...) or FluentRule::password(?int $min, ...).
        // Promoting a labeled `field('Meta')` to `array('Meta')` would rebind
        // 'Meta' to $keys (wrong). Only promote to a label-first target when
        // field() actually has args.
        if ($root->args !== [] && ! in_array($targetClass, self::LABEL_FIRST_TARGETS, true)) {
            return null;
        }

        $root->name = new Identifier($factoryName);

        return $node;
    }

    /**
     * Trigger B (spec `password-email-factory-promotion.md`):
     * `FluentRule::string()->…->rule(Password::default())` → `FluentRule::password()`
     * (and `Password::min($literal)`, `Email::default()` analogs).
     *
     * Splices the matched `->rule(Password::*)` / `->rule(Email::default())` hop
     * out of the chain and rewrites the root factory. Safety-gated per spec §2b:
     * zero-arg source factory, no other `->rule()` payloads, no Conditionable
     * hops, and every non-rule chain modifier must be available on the target
     * rule class (PasswordRule lacks `same()`/`different()`; collectiq dogfood
     * verified this would BadMethodCall-at-runtime without the gate).
     */
    /**
     * Walk `$methodCall->var` down until hitting the root of the chain.
     * Returns the root if it's a `StaticCall`; otherwise null. Handles
     * arbitrarily deep chains (FluentRule::field()->a()->b()->c()…).
     */
    private function walkToStaticCallRoot(MethodCall $methodCall): ?StaticCall
    {
        $current = $methodCall->var;

        while ($current instanceof MethodCall) {
            $current = $current->var;
        }

        return $current instanceof StaticCall ? $current : null;
    }

    /**
     * Gather all `->rule(...)` MethodCalls above `$root` up to and including
     * `$currentCall` (the call we fired on). Other method hops in the chain
     * (`->required()`, `->nullable()`, etc.) are skipped — only `->rule()`
     * payloads constrain the promotion.
     *
     * @return list<MethodCall>
     */
    private function collectRuleCallsFromRoot(StaticCall $root, MethodCall $currentCall): array
    {
        $ruleCalls = [];
        $hops = [];

        $current = $currentCall;

        while ($current instanceof MethodCall) {
            $hops[] = $current;
            $current = $current->var;
        }

        if ($current !== $root) {
            return [];
        }

        foreach (array_reverse($hops) as $hop) {
            if (! $hop->name instanceof Identifier) {
                continue;
            }

            $hopName = $hop->name->toString();

            // A Conditionable hop in the chain breaks the receiver-type
            // guarantee for everything downstream. Bailing here preserves
            // the invariant: promoting the factory is only safe when the
            // downstream `->rule(...)` lowering is also safe.
            if (in_array($hopName, self::CONDITIONABLE_HOPS, true)) {
                return [];
            }

            if ($hopName === 'rule') {
                $ruleCalls[] = $hop;
            }
        }

        return $ruleCalls;
    }

    /**
     * For each `->rule(...)` call in the chain, compute the set of typed
     * builders whose public methods include the call's target method
     * (`RULE_NAME_TO_METHOD[$name]`). Intersect across all calls. Returns
     * `null` when any call fails to parse or resolves to a rule name with no
     * v1 target method — those cases can't constrain the promotion and the
     * rewrite is unsafe without a strong signal.
     *
     * @param  list<MethodCall>  $ruleCalls
     * @return list<class-string>|null
     */
    private function intersectCompatibleBuilders(array $ruleCalls): ?array
    {
        $intersection = null;

        foreach ($ruleCalls as $ruleCall) {
            if (count($ruleCall->args) !== 1 || ! $ruleCall->args[0] instanceof Arg) {
                return null;
            }

            $parsed = $this->parseRulePayload($ruleCall->args[0]->value);

            if ($parsed === null) {
                return null;
            }

            [$ruleName] = $parsed;
            $targetMethod = SimplifyRuleWrappersRector::RULE_NAME_TO_METHOD[$ruleName] ?? null;

            if ($targetMethod === null) {
                return null;
            }

            $compatSet = $this->classesWithPublicMethod($targetMethod);

            $intersection = $intersection === null
                ? $compatSet
                : array_values(array_intersect($intersection, $compatSet));

            if ($intersection === []) {
                return null;
            }
        }

        return $intersection;
    }

    /**
     * Return the list of typed builder classes that declare `$method` as a
     * public member. Uses reflection directly (no cache) — this rector fires
     * per StaticCall and the class list is small (11 entries); the reflection
     * cost is negligible next to Rector's AST traversal.
     *
     * @return list<class-string>
     */
    private function classesWithPublicMethod(string $method): array
    {
        $matches = [];

        foreach (array_keys(self::TYPED_BUILDER_TO_FACTORY) as $class) {
            $reflection = new ReflectionClass($class);

            if (! $reflection->hasMethod($method)) {
                continue;
            }

            $methodReflection = $reflection->getMethod($method);

            if ($methodReflection->isPublic() && ! $methodReflection->isStatic()) {
                $matches[] = $class;
            }
        }

        return $matches;
    }
}
