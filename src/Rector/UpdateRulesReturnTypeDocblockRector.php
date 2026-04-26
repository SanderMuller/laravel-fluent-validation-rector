<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitor;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PostRector\Collector\UseNodesToAddCollector;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use ReflectionClass;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Rector\Concerns\AllowlistedRuleFactories;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsRulesShapedMethods;
use SanderMuller\FluentValidationRector\Rector\Concerns\IdentifiesLivewireClasses;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\NormalizesRulesDocblock;
use SanderMuller\FluentValidationRector\Rector\Concerns\QualifiesForRulesProcessing;
use SanderMuller\FluentValidationRector\RunSummary;
use SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\UpdateRulesReturnTypeDocblockRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Opt-in polish rule: narrows the `@return` PHPDoc annotation on `rules()`
 * methods to `array<string, FluentRuleContract>` when the returned array is
 * composed entirely of `FluentRule::*()` call chains.
 *
 * Activates only inside the POLISH set. Runtime behavior is untouched; only
 * the docblock shape changes, giving downstream static analyzers a narrower
 * type than the `ValidationRule|string|array<mixed>` wide-union the
 * conversion rectors emit mid-pipeline.
 *
 * @see UpdateRulesReturnTypeDocblockRectorTest
 */
final class UpdateRulesReturnTypeDocblockRector extends AbstractRector implements ConfigurableRectorInterface, DocumentedRuleInterface
{
    use AllowlistedRuleFactories;
    use DetectsInheritedTraits;
    use DetectsRulesShapedMethods;
    use IdentifiesLivewireClasses;
    use LogsSkipReasons;
    use NormalizesRulesDocblock;
    use QualifiesForRulesProcessing;

    /**
     * Re-declared on the using class so static analyzers can resolve
     * `UpdateRulesReturnTypeDocblockRector::TREAT_AS_FLUENT_COMPATIBLE`
     * from consumer `rector.php` files. PHP 8.2 permits reading a
     * trait constant via the using class at runtime, but intelephense
     * / PHPStan flag the expression as "Undefined class constant".
     * Values must match `AllowlistedRuleFactories`'s constants.
     */
    public const string TREAT_AS_FLUENT_COMPATIBLE = 'treat_as_fluent_compatible';

    public const string ALLOW_CHAIN_TAIL_ON_ALLOWLISTED = 'allow_chain_tail_on_allowlisted';

    /**
     * @param  array<mixed>  $configuration
     */
    public function configure(array $configuration): void
    {
        // Trait expects array<string, mixed>; ConfigurableRectorInterface
        // declares array<mixed>. Match the interface (contravariant) and
        // narrow inside the trait where needed.
        /** @var array<string, mixed> $typed */
        $typed = $configuration;
        $this->configureAllowlistedRuleFactoriesFrom($typed);
    }

    /**
     * Final emitted `@return` annotation body. Uses the short
     * `FluentRuleContract` name; the rector queues a `use` import for
     * `\SanderMuller\FluentValidation\Contracts\FluentRuleContract` via
     * `UseNodesToAddCollector` whenever the annotation is rewritten,
     * so static analyzers resolve the short name correctly. Pre-0.14.1
     * the body emitted the FQN inline, which Pint's
     * `fully_qualified_strict_types` cleaned up afterwards — every
     * rector run forced a follow-up Pint pass. Surfaced by collectiq
     * dogfood (2026-04-26).
     */
    private const string CONTRACT_ANNOTATION_BODY = 'array<string, FluentRuleContract>';

    /**
     * FQN queued via `UseNodesToAddCollector` whenever the annotation
     * is rewritten. String-referenced (not `::class`) so the rector
     * doesn't load the contract class at static-analysis time — the
     * package's `^1.0` constraint includes versions that may not ship
     * the contract under this exact namespace.
     */
    private const string CONTRACT_FQN = FluentRuleContract::class;

    public function __construct(private readonly UseNodesToAddCollector $useNodesToAddCollector)
    {
        RunSummary::registerShutdownHandler();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Narrow the @return annotation on rules() to array<string, FluentRuleContract> when every value is a FluentRule call chain.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class StorePostRequest extends FormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|string|array<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
            'body'  => FluentRule::string()->nullable(),
        ];
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class StorePostRequest extends FormRequest
{
    /**
     * @return array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
            'body'  => FluentRule::string()->nullable(),
        ];
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        if (! $this->qualifiesForRulesProcessing($node)) {
            // Silent skip — logging here would spam for every unrelated class
            // in the codebase. Class-context is the qualifying gate; only
            // skips INSIDE a qualifying class are interesting signal.
            return null;
        }

        $hasChanged = false;

        // Auto-detect of rules-shaped methods is class-wide; gate it
        // on the stricter qualifying signal so attribute-only classes
        // don't expand a single `#[FluentRules]` opt-in into class-wide
        // auto-detection (Codex 2026-04-26 catch).
        $allowsAutoDetect = $this->qualifiesForRulesProcessingClassWide($node);

        foreach ($node->getMethods() as $method) {
            if (! $this->isName($method, 'rules')
                && ! $this->hasFluentRulesAttribute($method)
                && ! ($allowsAutoDetect && $this->isRulesShapedMethod($method))) {
                continue;
            }

            if ($this->processRulesMethod($node, $method)) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $node : null;
    }

    private function processRulesMethod(Class_ $class, ClassMethod $method): bool
    {
        if (! $this->hasQualifyingReturnType($class, $method)) {
            return false;
        }

        $arrayNode = $this->singleLiteralArrayReturn($class, $method);

        if (! $arrayNode instanceof Array_) {
            return false;
        }

        $sawAllowlistedItem = false;

        if (! $this->allItemsAreFluentChains($class, $arrayNode, $sawAllowlistedItem)) {
            // Mixed fluent + allowlisted → silent skip, EXCEPT when the
            // existing docblock was already narrowed to FluentRuleContract.
            // That annotation is now stale (it claims all items are
            // FluentRuleContract instances, but the allowlisted item isn't).
            // Codex review (2026-04-24): emit a loud (default-mode) skip so
            // the consumer notices the contract drift.
            if ($sawAllowlistedItem) {
                $existingBody = $this->extractReturnAnnotationBody($method->getDocComment());

                if ($existingBody !== null && trim($existingBody) === self::CONTRACT_ANNOTATION_BODY) {
                    $methodName = $this->getName($method) ?? 'rules';
                    $this->logSkip($class, sprintf('%s() now mixes FluentRule chains with allowlisted rule factories — existing narrowed @return may be stale (mixed-array types cannot be expressed as FluentRuleContract)', $methodName));
                }
            }

            return false;
        }

        if (! $this->docblockIsNarrowable($class, $method)) {
            return false;
        }

        return $this->emitContractAnnotation($method);
    }

    private function hasQualifyingReturnType(Class_ $class, ClassMethod $method): bool
    {
        if ($method->returnType instanceof NullableType) {
            $methodName = $this->getName($method) ?? 'rules';
            $this->logSkip($class, sprintf('method %s() has nullable array return — cannot narrow contract', $methodName));

            return false;
        }

        // Non-array return types are out of scope — silent skip, not polish material.
        return $method->returnType instanceof Identifier && $method->returnType->toLowerString() === 'array';
    }

    private function singleLiteralArrayReturn(Class_ $class, ClassMethod $method): ?Array_
    {
        $returns = [];
        $this->traverseNodesWithCallable($method->stmts ?? [], function (Node $node) use (&$returns): ?int {
            if ($node instanceof FunctionLike) {
                return NodeVisitor::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
            }

            if ($node instanceof Return_) {
                $returns[] = $node;
            }

            return null;
        });

        if (count($returns) !== 1) {
            $methodName = $this->getName($method) ?? 'rules';
            $this->logSkip($class, sprintf('method %s() has multi-stmt return — cannot narrow contract', $methodName));

            return null;
        }

        $return = $returns[0];

        if (! $return->expr instanceof Array_) {
            $shape = $return->expr instanceof Expr
                ? (new ReflectionClass($return->expr))->getShortName()
                : 'null';
            $this->logSkip($class, sprintf('return expression is %s, not Array_ literal', $shape));

            return null;
        }

        return $return->expr;
    }

    /**
     * @param-out bool $sawAllowlistedItem
     */
    private function allItemsAreFluentChains(Class_ $class, Array_ $arrayNode, ?bool &$sawAllowlistedItem = null): bool
    {
        $sawAllowlistedItem = false;

        foreach ($arrayNode->items as $index => $item) {
            if (! $item instanceof ArrayItem) {
                $this->logSkip($class, sprintf('ArrayItem at index %d is malformed', $index));

                return false;
            }

            // php-parser 5.x represents `...$foo` inside an array as an
            // `ArrayItem` with `unpack=true` and `key=null`. Without this
            // branch, the null-key check below fires and logs a misleading
            // "key is not String_ / ClassConstFetch" reason — the real issue
            // is spread semantics (keys can't be determined statically).
            if ($item->unpack) {
                $this->logSkip($class, sprintf('encountered spread at index %d — cannot determine keys statically', $index));

                return false;
            }

            if (! $this->isStaticallyKnownStringKey($item->key)) {
                $this->logSkip($class, sprintf('ArrayItem key at index %d is not a statically-known string', $index));

                return false;
            }

            if ($this->isFluentRuleChainValue($item->value)) {
                continue;
            }

            // Consumer-declared allowlist: shapes like `Model::existsRule()`
            // or `new DutchPostcodeRule()` produce rule-compatible values
            // this rule can't statically prove are FluentRule chains. Don't
            // narrow the docblock (would be a type-lie for mixed arrays),
            // but don't log either — allowlisted items are documented
            // escape-hatch usage, not actionable noise.
            if ($this->isAllowlistedRuleFactory($item->value)) {
                $sawAllowlistedItem = true;

                continue;
            }

            $shape = (new ReflectionClass($item->value))->getShortName();
            $keyDesc = $item->key instanceof String_ ? $item->key->value : 'const';
            $this->logSkip($class, sprintf("value at key '%s' is not a FluentRule chain (shape: %s)", $keyDesc, $shape));

            return false;
        }

        // All non-allowlisted items are fluent chains. If ANY allowlisted
        // items were present, the array is mixed — bail silently rather
        // than narrow to `FluentRuleContract` (would lie about the
        // allowlisted items' type).
        return ! $sawAllowlistedItem;
    }

    /**
     * Accepts keys whose value is knowable at compile time and produces a
     * string: `String_`, `ClassConstFetch`, or a `Concat` tree whose every
     * leaf is one of those. Covers the Livewire nested-field idiom
     * `'prefix.' . Class::CONST => ...` which php-parser represents as a
     * `BinaryOp\Concat` — functionally a static string but not a single
     * `String_` node. Recurses through arbitrary Concat nesting.
     */
    private function isStaticallyKnownStringKey(?Expr $key): bool
    {
        if (! $key instanceof Expr) {
            return false;
        }

        if ($key instanceof String_ || $key instanceof ClassConstFetch) {
            return true;
        }

        if ($key instanceof Concat) {
            return $this->isStaticallyKnownStringKey($key->left)
                && $this->isStaticallyKnownStringKey($key->right);
        }

        return false;
    }

    private function docblockIsNarrowable(Class_ $class, ClassMethod $method): bool
    {
        $doc = $method->getDocComment();

        if ($doc instanceof Doc && stripos($doc->getText(), '@inheritdoc') !== false) {
            // Case-insensitive: PHPDoc spec allows both `@inheritDoc` (canonical)
            // and `@inheritdoc` (common lowercase variant).
            $this->logSkip($class, 'docblock contains @inheritDoc — respecting parent contract');

            return false;
        }

        $existingBody = $this->extractReturnAnnotationBody($doc);

        if ($existingBody === null) {
            return true;
        }

        // Already narrowed — silent idempotency guard. Accept both the
        // emitted FQN form and the short-name form a consumer may have
        // authored after adding a `use ...\FluentRuleContract;` statement.
        // Without the short-name form here, the rule would log 40+
        // "user-customized — respecting" verbose entries per run on
        // codebases that adopted the contract via import (mijntp dogfood
        // against 0.12.0).
        if (in_array(trim($existingBody), self::ALREADY_NARROWED_BODIES, true)) {
            return false;
        }

        if (! $this->canNarrowExistingBody($existingBody)) {
            $truncated = substr(trim($existingBody), 0, 80);
            $this->logSkip($class, sprintf("existing @return tag '%s' is user-customized — respecting", $truncated), verboseOnly: true, actionable: false);

            return false;
        }

        return true;
    }

    /**
     * Docblock bodies that equal the rule's emission target and therefore
     * must not be rewritten. Covers both the FQN form this rector emits and
     * the short-name form that results when a consumer adds a `use` import
     * for `FluentRuleContract` post-conversion.
     *
     * @var list<string>
     */
    private const array ALREADY_NARROWED_BODIES = [
        self::CONTRACT_ANNOTATION_BODY,
        'array<string, FluentRuleContract>',
    ];

    private function isFluentRuleChainValue(Expr $value): bool
    {
        $current = $value;

        while ($current instanceof MethodCall) {
            $current = $current->var;
        }

        if (! $current instanceof StaticCall) {
            return false;
        }

        $className = $this->getName($current->class);

        return $className === FluentRule::class;
    }

    /**
     * Returns the raw (joined, un-trimmed) body of the first `@return` tag,
     * or `null` when the docblock is missing the tag entirely. Continuation
     * lines are folded into a single-line string so the body can be compared
     * against `CONTRACT_ANNOTATION_BODY` / `STANDARD_RULES_ANNOTATION_BODY`
     * without whitespace fighting.
     */
    private function extractReturnAnnotationBody(?Doc $doc): ?string
    {
        if (! $doc instanceof Doc) {
            return null;
        }

        if (preg_match(self::RETURN_TAG_PATTERN, $doc->getText(), $matches) !== 1) {
            return null;
        }

        $body = (string) preg_replace('/\s*\n[ \t]*\*\s*/', ' ', $matches[2]);

        // A wrapped `@return` whose type starts on the next line leaves the
        // captured body with a leading `* ` (the continuation-line asterisk
        // the preceding `\s+` didn't consume). Strip so the body compares
        // cleanly against `STANDARD_RULES_ANNOTATION_BODY`.
        return (string) preg_replace('/^\s*\*\s*/', '', $body);
    }

    /**
     * Laravel validation contracts whose `array<string, X>` or `X[]` docblock
     * shape is safe to narrow from when every array item has already been
     * proven to be a FluentRule chain (condition 3). Fluent rule classes
     * implement all four contracts (see
     * `SanderMuller\FluentValidation\Rules\StringRule` etc.), so narrowing
     * from any of them to `FluentRuleContract` drops no valid type.
     *
     * @var list<string>
     */
    private const array LARAVEL_NARROWABLE_CONTRACT_SHORT_NAMES = [
        'ValidationRule',
        'DataAwareRule',
        'ValidatorAwareRule',
        'ImplicitRule',
        'Rule',
    ];

    private const string LARAVEL_CONTRACTS_NAMESPACE = 'Illuminate\\Contracts\\Validation\\';

    private function canNarrowExistingBody(string $body): bool
    {
        $trimmed = trim($body);

        if ($trimmed === 'array') {
            return true;
        }

        // Laravel's default IDE-generated docblock for rules() is
        // `array<string, mixed>`. Treating that as user-customized
        // false-positives on codebases that use IDE scaffolding and
        // never hand-edited the annotation. Narrowing `mixed` at the
        // item level to `FluentRuleContract` is strict (FluentRule
        // classes are always narrower than `mixed`) and condition 3
        // has already proven every item is a FluentRule chain.
        if ($trimmed === 'array<string, mixed>') {
            return true;
        }

        if ($this->annotationBodyMatchesStandardUnionExactlyOrProse($body)) {
            return true;
        }

        return $this->annotationBodyIsLaravelContractNarrowable($body);
    }

    /**
     * Matches `@return` bodies whose type is one of Laravel's validation
     * contracts keyed by string, in any of these idiomatic shapes:
     *
     *     array<string, ValidationRule>
     *     array<string, \Illuminate\Contracts\Validation\DataAwareRule>
     *     DataAwareRule[]
     *     \Illuminate\Contracts\Validation\ValidationRule[]
     *
     * Pure-prose trailing description accepted via the standard-body rules
     * (letters / punctuation only — no `|`, `&`, `<`, etc.).
     *
     * Pre-condition for this matcher to be called: the polish rule's
     * condition 3 has proven every array-item value is a FluentRule call
     * chain. Fluent rule classes implement all four listed contracts, so
     * narrowing from any of them to `FluentRuleContract` is strict.
     */
    private function annotationBodyIsLaravelContractNarrowable(string $body): bool
    {
        $trimmed = trim($body);

        foreach (self::LARAVEL_NARROWABLE_CONTRACT_SHORT_NAMES as $shortName) {
            foreach (["array<string, {$shortName}>", 'array<string, \\' . self::LARAVEL_CONTRACTS_NAMESPACE . "{$shortName}>"] as $generic) {
                if ($this->bodyExactOrProseTail($trimmed, $generic)) {
                    return true;
                }
            }

            // `DataAwareRule[]` shorthand — older PHPStan `T[]` form that
            // pre-dates generic array syntax. Semantically `array<int|string, T>`,
            // but our condition-3 check already guarantees string keys so
            // narrowing to `array<string, FluentRuleContract>` tightens the
            // key type legitimately. Real-world shape flagged by mijntp peer
            // dry-run against ~43 rules() methods.
            foreach (["{$shortName}[]", '\\' . self::LARAVEL_CONTRACTS_NAMESPACE . "{$shortName}[]"] as $shorthand) {
                if ($this->bodyExactOrProseTail($trimmed, $shorthand)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function bodyExactOrProseTail(string $body, string $prefix): bool
    {
        if ($body === $prefix) {
            return true;
        }

        if (! str_starts_with($body, $prefix)) {
            return false;
        }

        $remainder = substr($body, strlen($prefix));

        return preg_match('/^\s+[A-Za-z][A-Za-z0-9 ,.\'\-]*$/', $remainder) === 1;
    }

    private function emitContractAnnotation(ClassMethod $method): bool
    {
        $doc = $method->getDocComment();
        $contractTag = '@return ' . self::CONTRACT_ANNOTATION_BODY;

        if (! $doc instanceof Doc) {
            $method->setDocComment(new Doc(sprintf("/**\n * %s\n */", $contractTag)));
            $this->queueContractUseImport();

            return true;
        }

        $text = $doc->getText();

        if (preg_match(self::RETURN_TAG_PATTERN, $text) === 1) {
            $replaced = (string) preg_replace(
                self::RETURN_TAG_PATTERN,
                '$1 ' . self::CONTRACT_ANNOTATION_BODY,
                $text,
                1,
            );

            if ($replaced === $text) {
                return false;
            }

            $method->setDocComment(new Doc($replaced));
            $this->queueContractUseImport();

            return true;
        }

        // Docblock exists but has no @return tag — inject one before the
        // closing `*/`. Preserve existing content verbatim.
        //
        // Single-line shape `/** content */` can't be injected with a simple
        // `*/` replacement — doing so produces `/** content * @return ... */`
        // with the new tag inline with the original content. Expand single-line
        // to multi-line first, then fall through to the multi-line path.
        if (! str_contains($text, "\n")) {
            $text = $this->expandSingleLineDocblockToMultiline($text);
        }

        $injected = preg_replace(
            '/([ \t]*)\*\/\s*$/',
            "$1* {$contractTag}\n$1*/",
            $text,
            1,
        );

        if (! is_string($injected) || $injected === $text) {
            return false;
        }

        $method->setDocComment(new Doc($injected));
        $this->queueContractUseImport();

        return true;
    }

    /**
     * Queue the `use SanderMuller\FluentValidation\Contracts\FluentRuleContract;`
     * import via Rector's post-rector collector. Idempotent across
     * multiple methods in the same file — the collector dedups. Without
     * this, the docblock's short `FluentRuleContract` would resolve to
     * the wrong (or missing) symbol in static analyzers + IDEs. Pre-0.14.1
     * the rector emitted the FQN inline; Pint would clean it up
     * post-run, but every rector invocation forced a follow-up Pint
     * pass on every touched file. GH dogfood (2026-04-26).
     */
    private function queueContractUseImport(): void
    {
        $this->useNodesToAddCollector->addUseImport(new FullyQualifiedObjectType(self::CONTRACT_FQN));
    }

    /**
     * Converts `/** content *\/` to
     *
     *     /**
     *      * content
     *      *\/
     *
     * preserving the content verbatim. The inject path relies on matching the
     * trailing `*\/` on its own line; single-line docblocks don't have that,
     * so we expand them first. Content stripped of leading `/**` and trailing
     * `*\/` markers, with inner whitespace trimmed to avoid double-spacing.
     */
    private function expandSingleLineDocblockToMultiline(string $singleLine): string
    {
        $content = preg_replace('/^\/\*\*\s*|\s*\*\/$/', '', $singleLine);
        $content = trim((string) $content);

        if ($content === '') {
            return "/**\n */";
        }

        return "/**\n * {$content}\n */";
    }
}
