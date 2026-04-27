<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsRulesShapedMethods;
use SanderMuller\FluentValidationRector\Rector\Concerns\IdentifiesLivewireClasses;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\ManagesNamespaceImports;
use SanderMuller\FluentValidationRector\Rector\Concerns\NormalizesRulesDocblock;
use SanderMuller\FluentValidationRector\Rector\Concerns\QualifiesForRulesProcessing;
use SanderMuller\FluentValidationRector\RunSummary;
use SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\GroupWildcardRulesToEachRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Groups flat wildcard/dotted validation keys into nested each()/children() calls.
 *
 * Before: 'items' => ..., 'items.*.name' => ..., 'items.*.email' => ...
 * After:  'items' => FluentRule::array()->required()->each(['name' => ..., 'email' => ...])
 *
 * Applies to Livewire components too: `sandermuller/laravel-fluent-validation` 1.7+
 * flattens nested each()/children() back to wildcard keys at runtime via
 * `HasFluentValidation::getRules()`, so Livewire's wildcard handling sees the
 * flat form it expects. Pre-1.7 main-package installs would break at runtime;
 * composer constraint enforces >= 1.7.1.
 *
 * @see GroupWildcardRulesToEachRectorTest
 */
final class GroupWildcardRulesToEachRector extends AbstractRector implements DocumentedRuleInterface
{
    use DetectsInheritedTraits;
    use DetectsRulesShapedMethods;
    use IdentifiesLivewireClasses;
    use LogsSkipReasons;
    use ManagesNamespaceImports;
    use NormalizesRulesDocblock;
    use QualifiesForRulesProcessing;

    private const int MAX_NESTING_DEPTH = 4;

    public function __construct()
    {
        RunSummary::registerShutdownHandler();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Group flat wildcard/dotted validation keys into nested each()/children() calls.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => FluentRule::array()->required(),
            'items.*.name' => FluentRule::string()->required(),
            'items.*.email' => FluentRule::email()->required(),
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
    public function rules(): array
    {
        return [
            'items' => FluentRule::array()->required()->each([
                'name' => FluentRule::string()->required(),
                'email' => FluentRule::email()->required(),
            ]),
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
        return [Namespace_::class];
    }

    /**
     * Map of local class constant name → string value.
     * Built from the class's own constants for resolving `self::X` keys.
     *
     * @var array<string, string>
     */
    private array $localConstants = [];

    /**
     * The class currently under refactor. Set in `refactorClass()` so deep
     * helpers (`processGroup`, `collectGroupChainItems`, `indexConcatKey`,
     * `findTopLevelGroups`) can reach `logSkip()` without threading a
     * `Class_` arg through every signature. Reset to null on entry to keep
     * the rector reentrant across classes within a namespace.
     */
    private ?Class_ $currentClass = null;

    /**
     * Set to true when any class in the current namespace synthesized a
     * FluentRule factory call (FluentRule::array() / FluentRule::field()).
     * Triggers insertion of `use SanderMuller\FluentValidation\FluentRule;`
     * so the emitted short `FluentRule::` references resolve correctly.
     */
    private bool $needsFluentRuleImport = false;

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Namespace_) {
            return null;
        }

        $this->needsFluentRuleImport = false;
        $hasChanged = false;

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Class_) {
                continue;
            }

            if ($this->refactorClass($stmt)) {
                $hasChanged = true;
            }
        }

        if (! $hasChanged) {
            return null;
        }

        if ($this->needsFluentRuleImport) {
            $this->ensureUseImportInNamespace($node, FluentRule::class);
        }

        return $node;
    }

    /**
     * Internal helper: emit a skip-log entry tied to the class currently
     * being refactored. Decision-point bails inside deep helpers
     * (`processGroup`, `collectGroupChainItems`, `indexConcatKey`,
     * `findTopLevelGroups`) call this rather than `logSkip` directly so
     * they don't have to thread the `Class_` arg through their signatures.
     * No-ops when `$currentClass` isn't set, which only happens if a
     * caller bypasses `refactorClass()` — defensive, shouldn't trigger
     * in normal flow.
     */
    private function logGroupSkip(string $reason): void
    {
        if ($this->currentClass instanceof Class_) {
            $this->logSkip($this->currentClass, $reason);
        }
    }

    /** @phpstan-impure */
    private function refactorClass(Class_ $class): bool
    {
        // Class-qualification gate (silent bail). Without this, every
        // ClassLike with a `rules()` method gets walked — including
        // unrelated Domain entities. See QualifiesForRulesProcessing.
        if (! $this->qualifiesForRulesProcessing($class)) {
            return false;
        }

        // Shape-change gate. The wildcard-fold transformation rewrites
        // sibling `'*.foo'` keys into `'*' => array()->children([...])`
        // — a structurally correct equivalent under FormRequest /
        // fluent-trait / Livewire dispatch, but a runtime break for
        // Validator subclasses qualifying solely via `#[FluentRules]`
        // whose parent class postprocesses `rulesWithoutPrefix()`
        // output (e.g. prefix-prepend walks). The fold's nested-key
        // shape doesn't round-trip through such postprocessors.
        // Surfaced on hihaho's JsonAdaptiveSubjectImportValidator,
        // 2026-04-27. Skip-and-log so the user knows why.
        if (! $this->qualifiesForShapeChange($class)) {
            $this->logSkip(
                $class,
                'shape-changing rector skipped on Validator subclass — parent class may postprocess rules() output and the each()/children() shape is incompatible. Wrap manually if you have audited the parent\'s behavior.',
            );

            return false;
        }

        // Build map of local string constants for resolving self::X keys
        $this->localConstants = $this->collectLocalStringConstants($class);
        $this->currentClass = $class;

        try {
            $hasChanged = false;

            // Auto-detect of rules-shaped methods is class-wide; gate
            // it on the stricter qualifying signal so attribute-only
            // classes don't expand a single `#[FluentRules]` opt-in
            // into class-wide auto-detection (Codex 2026-04-26 catch).
            $allowsAutoDetect = $this->qualifiesForRulesProcessingClassWide($class);

            foreach ($class->getMethods() as $method) {
                // Denylist always wins. A `#[FluentRules]` attribute on
                // `casts()`, `messages()`, etc. is a mistake — the
                // shared layer-2 warning fires from
                // `QualifiesForRulesProcessing::warnDenylistedAttributedMethods()`;
                // this gate refuses to walk the body regardless of the
                // attribute.
                if ($this->isNonRulesMethodName($this->getName($method))) {
                    continue;
                }

                if (! $this->isName($method, 'rules')
                    && ! $this->hasFluentRulesAttribute($method)
                    && ! ($allowsAutoDetect && $this->isRulesShapedMethod($method))) {
                    continue;
                }

                $methodChanged = false;

                $this->traverseNodesWithCallable($method, function (Node $inner) use (&$methodChanged): ?Return_ {
                    if (! $inner instanceof Return_ || ! $inner->expr instanceof Array_) {
                        return null;
                    }

                    if ($this->groupRulesArray($inner->expr)) {
                        $methodChanged = true;

                        return $inner;
                    }

                    return null;
                });

                if ($methodChanged) {
                    $hasChanged = true;

                    // Grouping flat wildcards into `array()->each(...)` changes the
                    // terminal Rule subclass (e.g. StringRule → ArrayRule), which
                    // invalidates narrow author-written `@return` annotations. See
                    // NormalizesRulesDocblock for rationale; mijntp's 0.4.14 run
                    // surfaced 5 production files with this exact shape.
                    $this->normalizeRulesDocblockIfStale($method);
                }
            }

            return $hasChanged;
        } finally {
            // Clear class-attribution state on exit so a stray downstream
            // `logGroupSkip()` call (e.g. after a future refactor adds a
            // new emit-site path) cannot misattribute to a stale class.
            $this->currentClass = null;
        }
    }

    /**
     * Collect string-valued class constants from the current class.
     *
     * @return array<string, string>  constant name → string value
     */
    private function collectLocalStringConstants(Class_ $class): array
    {
        $constants = [];

        foreach ($class->getConstants() as $classConst) {
            foreach ($classConst->consts as $const) {
                if ($const->value instanceof String_) {
                    $constants[$const->name->toString()] = $const->value->value;
                }
            }
        }

        return $constants;
    }

    // ─── Array grouping ──────────────────────────────────────────────────

    /**
     * Process a rules array — detect groupable keys and nest them.
     */
    private function groupRulesArray(Array_ $array): bool
    {
        /** @var array<string, array{index: int, value: Expr}> */
        $entries = [];

        /** @var array<string, array{prefix: Expr, suffixKey: string}> */
        $concatEntries = [];

        /** @var list<array{item: ArrayItem, constExpr: ClassConstFetch, value: Expr}> */
        $wildcardPrefixConstEntries = [];

        foreach ($array->items as $index => $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            $this->indexRuleItem($item, $index, $entries, $concatEntries, $wildcardPrefixConstEntries);
        }

        $hasChanged = false;

        // Existing fold pipeline. Operates on `$entries` + `$concatEntries`.
        // The new-kind items (wildcard-prefix-const) aren't in `$entries`,
        // so this pass ignores them; the two folds are disjoint.
        if ($entries !== []) {
            $groups = $this->findTopLevelGroups($entries);

            if ($groups !== []) {
                $hasChanged = $this->applyGroups($array, $groups, $entries, $concatEntries);
            }
        }

        // 0.19.0 wildcard-prefix concat fold. Runs after the existing fold
        // because it identifies items by reference (ArrayItem identity), so
        // index shifts from the existing fold don't matter.
        if ($wildcardPrefixConstEntries !== []
            && $this->applyWildcardPrefixConstFold($array, $wildcardPrefixConstEntries, $entries)) {
            return true;
        }

        return $hasChanged;
    }

    /**
     * Classify a single ArrayItem by its key form (String_, ClassConstFetch, Concat) and
     * append it to $entries / $concatEntries. Unsupported key forms are ignored.
     *
     * @param  array<string, array{index: int, value: Expr}>  $entries
     * @param  array<string, array{prefix: Expr, suffixKey: string}>  $concatEntries
     * @param  list<array{item: ArrayItem, constExpr: ClassConstFetch, value: Expr}>  $wildcardPrefixConstEntries
     */
    private function indexRuleItem(ArrayItem $item, int $index, array &$entries, array &$concatEntries, array &$wildcardPrefixConstEntries): void
    {
        if ($item->key instanceof String_) {
            $entries[$item->key->value] = ['index' => $index, 'value' => $item->value];

            return;
        }

        if ($item->key instanceof ClassConstFetch) {
            $this->indexClassConstKey($item->key, $item->value, $index, $entries, $concatEntries);

            return;
        }

        if ($item->key instanceof Concat) {
            $this->indexConcatKey($item->key, $item->value, $index, $item, $entries, $concatEntries, $wildcardPrefixConstEntries);
        }
    }

    /**
     * @param  array<string, array{index: int, value: Expr}>  $entries
     * @param  array<string, array{prefix: Expr, suffixKey: string}>  $concatEntries
     */
    private function indexClassConstKey(ClassConstFetch $keyExpr, Expr $value, int $index, array &$entries, array &$concatEntries): void
    {
        $resolvedKey = $this->resolveClassConstToString($keyExpr) ?? $this->classConstToSyntheticKey($keyExpr);

        if ($resolvedKey === null) {
            return;
        }

        $entries[$resolvedKey] = ['index' => $index, 'value' => $value];
        $concatEntries[$resolvedKey] = ['prefix' => $keyExpr, 'suffixKey' => ''];
    }

    /**
     * @param  array<string, array{index: int, value: Expr}>  $entries
     * @param  array<string, array{prefix: Expr, suffixKey: string}>  $concatEntries
     * @param  list<array{item: ArrayItem, constExpr: ClassConstFetch, value: Expr}>  $wildcardPrefixConstEntries
     */
    private function indexConcatKey(Concat $keyExpr, Expr $value, int $index, ArrayItem $item, array &$entries, array &$concatEntries, array &$wildcardPrefixConstEntries): void
    {
        $parsed = $this->parseConcatKey($keyExpr);

        if ($parsed === null) {
            $this->logGroupSkip(
                'concat key too complex to parse for grouping — only static class-constant prefixes (e.g. self::FOO) followed by a dotted-string suffix are supported',
            );

            return;
        }

        if ($parsed['kind'] === 'string_prefix_const_suffix') {
            // 0.19.0 wildcard-prefix concat fold. Capture the ArrayItem
            // by reference so applyWildcardPrefixConstFold can identify
            // and replace it later, regardless of any index shifts from
            // the parallel existing-shape fold.
            $wildcardPrefixConstEntries[] = [
                'item' => $item,
                'constExpr' => $parsed['constExpr'],
                'value' => $value,
            ];

            return;
        }

        $resolved = $this->resolveConcatToString($parsed) ?? $parsed['prefixId'] . $parsed['suffix'];

        $entries[$resolved] = ['index' => $index, 'value' => $value];
        $concatEntries[$resolved] = ['prefix' => $parsed['prefixExpr'], 'suffixKey' => $parsed['suffix']];
    }

    // ─── Concat key parsing ──────────────────────────────────────────────

    /**
     * Resolve a ClassConstFetch to its string value if it's a local self/static constant
     * whose value we collected at the start of refactor().
     */
    private function resolveClassConstToString(ClassConstFetch $expr): ?string
    {
        if (! $expr->class instanceof Name) {
            return null;
        }

        $className = $expr->class->toString();

        // Only resolve self::X, static::X, or the current class's constants
        if (! in_array(strtolower($className), ['self', 'static'], true)) {
            return null;
        }

        $constName = $this->getName($expr->name);

        if ($constName === null) {
            return null;
        }

        return $this->localConstants[$constName] ?? null;
    }

    /**
     * Resolve a parsed concat key to a full string if its prefix expression resolves to a constant.
     *
     * Only handles the `const_prefix_string_suffix` kind. Callers
     * MUST gate on `$parsed['kind'] === 'const_prefix_string_suffix'`
     * before invoking; the `string_prefix_const_suffix` kind has
     * different fields and a different fold semantic.
     *
     * @param  array{kind: 'const_prefix_string_suffix', prefixExpr: ClassConstFetch, prefix: string, suffix: string, prefixId: string}  $parsed
     */
    private function resolveConcatToString(array $parsed): ?string
    {
        $prefixValue = $this->resolveClassConstToString($parsed['prefixExpr']);

        if ($prefixValue === null) {
            return null;
        }

        return $prefixValue . $parsed['suffix'];
    }

    private function classConstToSyntheticKey(ClassConstFetch $expr): ?string
    {
        $className = $this->getName($expr->class);
        $constName = $this->getName($expr->name);

        if ($className === null || $constName === null) {
            return null;
        }

        return '__concat_' . $className . '::' . $constName . '__';
    }

    /**
     * Parse a Concat key expression into one of two shape kinds.
     *
     * **Kind `const_prefix_string_suffix`** — existing supported shape:
     *   `self::INTERACTIONS . '.*.type'`
     *   → prefixExpr: ClassConstFetch(self, INTERACTIONS)
     *   → suffix: '.*.type'
     *   → prefixId: '__concat_self::INTERACTIONS__'
     *
     * **Kind `string_prefix_const_suffix`** — new in 0.19, peer-shared:
     *   `'*.' . InteractionGroup::NAME`
     *   → prefix: '*.'
     *   → constExpr: ClassConstFetch(InteractionGroup, NAME)
     *
     * The two kinds drive different fold output paths in the
     * indexer/grouper/emitter chain. The new kind folds to
     * `'*' => array()->children([CONST => ..., CONST_2 => ...])` with
     * the `ClassConstFetch` preserved verbatim as the children-array
     * key (NOT string-literalized — refactor safety + intent
     * preservation).
     *
     * @return array{kind: 'const_prefix_string_suffix', prefixExpr: ClassConstFetch, prefix: string, suffix: string, prefixId: string}|array{kind: 'string_prefix_const_suffix', prefix: string, constExpr: ClassConstFetch}|null
     */
    private function parseConcatKey(Concat $concat): ?array
    {
        // Flatten the concat chain into parts
        $parts = $this->flattenConcat($concat);

        $stringPrefixConstSuffix = $this->matchStringPrefixConstSuffix($parts);

        if ($stringPrefixConstSuffix !== null) {
            return $stringPrefixConstSuffix;
        }

        // Existing shape: ClassConstFetch . String_('.suffix') [. String_('.more')]…
        $prefixExpr = null;
        $prefixId = '';
        $suffix = '';
        $foundSuffix = false;

        foreach ($parts as $part) {
            if ($part instanceof String_) {
                if (! $foundSuffix && $prefixExpr instanceof ClassConstFetch) {
                    // This string comes after an expression — it's the suffix start
                    $suffix = $part->value;
                    $foundSuffix = true;
                } elseif ($foundSuffix) {
                    $suffix .= $part->value;
                } else {
                    // String before any expression — part of prefix? Skip for now
                    return null;
                }
            } elseif ($part instanceof ClassConstFetch) {
                if ($foundSuffix) {
                    // Expression in the suffix — too complex for Phase 2
                    return null;
                }

                if ($prefixExpr instanceof ClassConstFetch) {
                    // Multiple expression parts — too complex
                    return null;
                }

                $prefixExpr = $part;
                $className = $this->getName($part->class) ?? 'unknown';
                $constName = $this->getName($part->name) ?? 'unknown';
                $prefixId = '__concat_' . $className . '::' . $constName . '__';
            } else {
                // Variable or method call — can't parse
                return null;
            }
        }

        if (! $prefixExpr instanceof ClassConstFetch || $suffix === '') {
            return null;
        }

        // Suffix must start with '.' (the separator between prefix and path)
        if (! str_starts_with($suffix, '.')) {
            return null;
        }

        return [
            'kind' => 'const_prefix_string_suffix',
            'prefixExpr' => $prefixExpr,
            'prefix' => $prefixId,
            'suffix' => $suffix,
            'prefixId' => $prefixId,
        ];
    }

    /**
     * Match the `String_('*.') . ClassConstFetch` shape. Strict on the
     * prefix value — `'*. ' . CONST` (trailing space) or
     * `'*.foo.' . CONST` falls through to the existing parser path so
     * we don't silently accept malformed wildcard prefixes.
     *
     * @param  list<Expr>  $parts
     * @return array{kind: 'string_prefix_const_suffix', prefix: string, constExpr: ClassConstFetch}|null
     */
    private function matchStringPrefixConstSuffix(array $parts): ?array
    {
        if (count($parts) !== 2) {
            return null;
        }

        if (! $parts[0] instanceof String_ || $parts[0]->value !== '*.') {
            return null;
        }

        if (! $parts[1] instanceof ClassConstFetch) {
            return null;
        }

        return [
            'kind' => 'string_prefix_const_suffix',
            'prefix' => '*.',
            'constExpr' => $parts[1],
        ];
    }

    /**
     * Flatten a Concat chain into ordered parts (left to right).
     *
     * @return list<Expr>
     */
    private function flattenConcat(Expr $expr): array
    {
        if ($expr instanceof Concat) {
            return [...$this->flattenConcat($expr->left), ...$this->flattenConcat($expr->right)];
        }

        return [$expr];
    }

    /**
     * Find top-level keys that have children (dotted or wildcard).
     *
     * @param  array<string, array{index: int, value: Expr}>  $entries
     * @return list<array{parent: string, wildcardKeys: array<string, string>, fixedKeys: array<string, string>, wildcardParentKey: ?string}>
     */
    private function findTopLevelGroups(array $entries): array
    {
        $groups = [];
        $allKeys = array_keys($entries);

        // Find unique top-level prefixes
        $prefixes = [];

        foreach ($allKeys as $key) {
            $dotPos = strpos($key, '.');

            if ($dotPos === false) {
                continue; // No dot — not a child key
            }

            $prefix = substr($key, 0, $dotPos);

            if (! isset($prefixes[$prefix])) {
                $prefixes[$prefix] = [];
            }

            $prefixes[$prefix][] = $key;
        }

        foreach ($prefixes as $prefix => $childKeys) {
            $wildcardKeys = [];
            $fixedKeys = [];
            $wildcardParentKey = null;
            $hasDoubleWildcard = false;

            foreach ($childKeys as $childKey) {
                $suffix = substr($childKey, strlen($prefix) + 1); // after "prefix."

                // Bail on keys containing '**' (consecutive wildcards) or '*' as a non-first segment
                // within nested paths — these can't be safely grouped.
                if ($this->hasInvalidWildcard($suffix)) {
                    $hasDoubleWildcard = true;
                    break;
                }

                if ($suffix === '*') {
                    $wildcardParentKey = $childKey;
                } elseif (str_starts_with($suffix, '*.')) {
                    $wildcardSuffix = substr($suffix, 2);
                    $childSegment = explode('.', $wildcardSuffix)[0];
                    $wildcardKeys[$childKey] = $childSegment;
                } else {
                    $childSegment = explode('.', $suffix)[0];
                    $fixedKeys[$childKey] = $childSegment;
                }
            }

            if ($hasDoubleWildcard) {
                $this->logGroupSkip(sprintf(
                    "double wildcard or non-first '*' in key suffix under '%s' — cannot fold to nested each()",
                    $prefix,
                ));

                continue; // Skip this group entirely
            }

            // Emit a group when there are nested children OR a flat wildcard
            // parent (items.*) alone — the flat case collapses into the parent's
            // ->each(<scalar>) via the gap-2 branch in applyGroups().
            if ($wildcardKeys !== [] || $fixedKeys !== [] || $wildcardParentKey !== null) {
                $groups[] = [
                    'parent' => $prefix,
                    'wildcardKeys' => $wildcardKeys,
                    'fixedKeys' => $fixedKeys,
                    'wildcardParentKey' => $wildcardParentKey,
                ];
            }
        }

        return $groups;
    }

    /**
     * Build nested value for children, recursively handling sub-groups.
     *
     * @param  array<string, string>  $keyToSegment  full key → first child segment
     * @param  array<string, array{index: int, value: Expr}>  $entries
     * @param  string  $prefix  e.g. "items.*" for wildcard children
     * @param  list<int>  $consumed  indices to remove (mutated by reference)
     * @return list<ArrayItem>
     */
    private function buildNestedItems(array $keyToSegment, array $entries, string $prefix, array &$consumed, int $depth = 0): array
    {
        if ($depth > self::MAX_NESTING_DEPTH) {
            return []; // Safety: don't recurse too deep
        }

        /** @var array<string, list<string>> */
        $segmentToKeys = [];

        foreach ($keyToSegment as $fullKey => $segment) {
            $segmentToKeys[$segment][] = $fullKey;
        }

        $items = [];

        foreach ($segmentToKeys as $segment => $keys) {
            if (count($keys) === 1 && $keys[0] === $prefix . '.' . $segment) {
                $items[] = new ArrayItem($entries[$keys[0]]['value'], new String_($segment));
                $consumed[] = $entries[$keys[0]]['index'];

                continue;
            }

            $items[] = new ArrayItem(
                $this->buildNestedChildValue($keys, $entries, $prefix . '.' . $segment, $consumed, $depth),
                new String_($segment),
            );
        }

        return $items;
    }

    /**
     * Build a single nested child value that has sub-children (wildcard and/or fixed).
     *
     * @param  list<string>  $keys
     * @param  array<string, array{index: int, value: Expr}>  $entries
     * @param  list<int>  $consumed
     */
    private function buildNestedChildValue(array $keys, array $entries, string $directKey, array &$consumed, int $depth): Expr
    {
        $childValue = null;

        if (isset($entries[$directKey])) {
            $childValue = $entries[$directKey]['value'];
            $consumed[] = $entries[$directKey]['index'];
        }

        $partitioned = $this->partitionSubChildren($keys, $directKey);

        if ($childValue === null) {
            $childValue = $this->buildFluentRuleFactoryCall('field');
        }

        if ($partitioned['subWildcardParent'] !== null && isset($entries[$partitioned['subWildcardParent']])) {
            $consumed[] = $entries[$partitioned['subWildcardParent']]['index'];
        }

        if ($partitioned['subWildcard'] !== []) {
            $eachItems = $this->buildNestedItems($partitioned['subWildcard'], $entries, $directKey . '.*', $consumed, $depth + 1);

            if ($eachItems !== []) {
                $childValue = new MethodCall($childValue, new Identifier('each'), [
                    new Arg($this->multilineArray($eachItems)),
                ]);
            }
        }

        if ($partitioned['subFixed'] !== []) {
            $childrenItems = $this->buildNestedItems($partitioned['subFixed'], $entries, $directKey, $consumed, $depth + 1);

            if ($childrenItems !== []) {
                $childValue = new MethodCall($childValue, new Identifier('children'), [
                    new Arg($this->multilineArray($childrenItems)),
                ]);
            }
        }

        return $childValue;
    }

    /**
     * Partition sub-children under $directKey into wildcard, fixed, and a lone wildcard parent.
     *
     * @param  list<string>  $keys
     * @return array{subWildcard: array<string, string>, subFixed: array<string, string>, subWildcardParent: ?string}
     */
    private function partitionSubChildren(array $keys, string $directKey): array
    {
        $subWildcard = [];
        $subFixed = [];
        $subWildcardParent = null;

        foreach ($keys as $fullKey) {
            if ($fullKey === $directKey) {
                continue;
            }

            $subSuffix = substr($fullKey, strlen($directKey) + 1);

            if ($subSuffix === '*') {
                $subWildcardParent = $fullKey;
            } elseif (str_starts_with($subSuffix, '*.')) {
                $subWildcard[$fullKey] = explode('.', substr($subSuffix, 2))[0];
            } else {
                $subFixed[$fullKey] = explode('.', $subSuffix)[0];
            }
        }

        return [
            'subWildcard' => $subWildcard,
            'subFixed' => $subFixed,
            'subWildcardParent' => $subWildcardParent,
        ];
    }

    // ─── Wildcard-prefix concat fold (0.19.0) ────────────────────────────

    /**
     * Fold sibling rule keys shaped `'*.' . CONST` into
     * `'*' => FluentRule::array()->children([CONST => …, CONST_2 => …])`.
     *
     * Validates per spec §2 cases (c) + (d) before folding:
     *
     * - Case (d) — sibling `'*'` rule already exists. Bail-and-log.
     * - Case (c) — const value collision (two consts resolving to same
     *   string). Bail-and-log.
     *
     * On clean validation, builds the children() emit. Children's
     * `ClassConstFetch` keys are preserved VERBATIM (identity-equal,
     * not cloned) — refactor safety: a consumer renaming the const
     * value reflects in the rector's output without re-running, and
     * the call site reader sees the const reference instead of a
     * stringified value.
     *
     * Children appear in source order. Per spec §3: deterministic,
     * faithful to author intent, no git-blame archaeology cost.
     *
     * @param  list<array{item: ArrayItem, constExpr: ClassConstFetch, value: Expr}>  $entries
     * @param  array<string, array{index: int, value: Expr}>  $stringEntries
     */
    private function applyWildcardPrefixConstFold(Array_ $array, array $entries, array $stringEntries): bool
    {
        // Case (d) — `'*'` already exists at the top level of the
        // current array. Scan the LIVE `$array->items` (not the
        // stale `$entries` snapshot) so the check covers two
        // routes the snapshot misses:
        //
        // 1. The existing fold may have synthesized a `'*' => …`
        //    parent earlier in this pass (from literal `'*.foo'`
        //    keys). The snapshot was taken before that pass; the
        //    live scan sees the synthesized parent.
        // 2. Direct `ClassConstFetch` keys whose self/static const
        //    resolves to `'*'` (e.g. `self::STAR = '*'`) — the
        //    snapshot indexed those under a synthetic name, so
        //    the snapshot's `'*'` key check missed them.
        //
        // External consts with non-resolvable runtime values still
        // bypass the check; documented limitation in spec §2(c).
        if ($this->arrayHasTopLevelStarKey($array)) {
            $this->logGroupSkip(
                "wildcard fold target '*' is already used by a non-wildcard sibling rule; folding would clobber it. Move the '*' rule into the wildcard set's children([...]) body explicitly, or rename the wildcard parent.",
            );

            return false;
        }

        // Reference $stringEntries so PHPStan doesn't flag it as
        // unused. The live scan above replaces the snapshot lookup,
        // but the parameter stays in the signature for symmetry
        // with the existing-fold pipeline (potentially used by
        // future validation passes).
        unset($stringEntries);

        // Case (c) — const value collision. Two consts evaluating to the
        // same string would both produce key '<value>' in children([…]),
        // and the fold can't deterministically pick which CONST to keep.
        // Resolved values for collision check ONLY; the emit preserves
        // the ClassConstFetch node verbatim.
        $seenValues = [];

        foreach ($entries as $entry) {
            $resolvedValue = $this->resolveClassConstToString($entry['constExpr']);

            if ($resolvedValue === null) {
                continue;
            }

            if (isset($seenValues[$resolvedValue])) {
                $this->logGroupSkip(sprintf(
                    "const value collision in wildcard fold: %s and %s both evaluate to '%s'. Refactor to use literal string keys, or remove the duplicate.",
                    $this->formatClassConstFetch($seenValues[$resolvedValue]),
                    $this->formatClassConstFetch($entry['constExpr']),
                    $resolvedValue,
                ));

                return false;
            }

            $seenValues[$resolvedValue] = $entry['constExpr'];
        }

        // Build the children([...]) inner array. Each entry produces
        // `ArrayItem(key: <ClassConstFetch verbatim>, value: <Expr verbatim>)`.
        // Source order preserved: $entries was filled in iteration order
        // during indexing.
        $childItems = [];

        foreach ($entries as $entry) {
            $childItems[] = new ArrayItem($entry['value'], $entry['constExpr']);
        }

        $foldExpr = new MethodCall(
            $this->buildFluentRuleFactoryCall('array'),
            new Identifier('children'),
            [new Arg($this->multilineArray($childItems))],
        );

        $foldItem = new ArrayItem($foldExpr, new String_('*'));

        // Splice: identify by AST identity (===), not by index (which
        // may have shifted from the existing fold's prior pass).
        $matchedItems = array_column($entries, 'item');
        $newItems = [];
        $foldInserted = false;

        foreach ($array->items as $item) {
            if (in_array($item, $matchedItems, true)) {
                if (! $foldInserted) {
                    $newItems[] = $foldItem;
                    $foldInserted = true;
                }

                continue;
            }

            $newItems[] = $item;
        }

        // Defensive: if no captured items survived to this pass (the
        // existing fold's mutations or some other path removed them),
        // don't overwrite $array->items with a no-op assignment AND
        // don't claim a change that didn't happen — `$hasChanged`
        // propagating true would mark the file as mutated when it
        // wasn't, which Rector caches against. Should not trigger in
        // normal flow (the existing fold and the new fold are disjoint
        // by AST shape) but defends against future indexer changes.
        if (! $foldInserted) {
            return false;
        }

        $array->items = $newItems;

        return true;
    }

    /**
     * Live-scan check for case (d) clobber detection. Walks the
     * current `$array->items` and returns true if any item's key
     * resolves to the literal string `'*'`. Catches:
     *
     * - String_('*') literal keys (most common case)
     * - ClassConstFetch keys whose self/static const resolves to
     *   '*' (rare but possible — `self::STAR = '*'`)
     * - Synthesized `'*' => …` parents from a prior fold pass
     *   (the existing fold's `applyGroups` may emit these from
     *   literal `'*.foo'` siblings; the new fold must not collide)
     *
     * External-class consts whose runtime value is `'*'` aren't
     * resolved here (per spec §2(c) implementation scope) — those
     * fall outside the check, accepted as documented limitation.
     */
    private function arrayHasTopLevelStarKey(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($item->key instanceof String_ && $item->key->value === '*') {
                return true;
            }

            if ($item->key instanceof ClassConstFetch
                && $this->resolveClassConstToString($item->key) === '*'
            ) {
                return true;
            }
        }

        return false;
    }

    private function formatClassConstFetch(ClassConstFetch $expr): string
    {
        $className = $this->getName($expr->class) ?? 'unknown';
        $constName = $this->getName($expr->name) ?? 'unknown';

        return $className . '::' . $constName;
    }

    // ─── Apply groups ────────────────────────────────────────────────────

    /**
     * @param  list<array{parent: string, wildcardKeys: array<string, string>, fixedKeys: array<string, string>, wildcardParentKey: ?string}>  $groups
     * @param  array<string, array{index: int, value: Expr}>  $entries
     * @param  array<string, array{prefix: Expr, suffixKey: string}>  $concatEntries
     */
    private function applyGroups(Array_ $array, array $groups, array $entries, array $concatEntries = []): bool
    {
        /** @var list<int> */
        $indicesToRemove = [];

        /** @var array<int, Expr> */
        $indicesToUpdate = [];

        /** @var list<array{position: int, item: ArrayItem}> */
        $insertions = [];

        foreach ($groups as $group) {
            $result = $this->processGroup($group, $entries, $concatEntries);

            if ($result === null) {
                continue;
            }

            if ($result['parentIndex'] !== null) {
                $indicesToUpdate[$result['parentIndex']] = $result['parentValue'];
            } elseif ($result['insertion'] !== null) {
                $insertions[] = $result['insertion'];
            }

            $indicesToRemove = [...$indicesToRemove, ...$result['consumed']];
        }

        if ($indicesToRemove === [] && $indicesToUpdate === [] && $insertions === []) {
            return false;
        }

        // Apply updates (safe — doesn't change indices)
        foreach ($indicesToUpdate as $idx => $newValue) {
            $array->items[$idx]->value = $newValue;
        }

        // Apply insertions + removals in one pass: build new items array
        $indicesToRemove = array_unique($indicesToRemove);
        $removeSet = array_flip($indicesToRemove);

        // Sort insertions by position (descending) so we can insert in reverse
        usort($insertions, static fn (array $a, array $b) => $b['position'] <=> $a['position']);

        // Remove consumed items first
        $newItems = [];

        foreach ($array->items as $idx => $item) {
            if (! isset($removeSet[$idx])) {
                $newItems[] = $item;
            }
        }

        // Insert synthetic parents at approximate positions
        foreach ($insertions as $insertion) {
            // Find the right position in the filtered array
            $pos = 0;

            foreach (array_keys($array->items) as $origIdx) {
                if ($origIdx >= $insertion['position']) {
                    break;
                }

                if (! isset($removeSet[$origIdx])) {
                    ++$pos;
                }
            }

            array_splice($newItems, $pos, 0, [$insertion['item']]);
        }

        $array->items = $newItems;

        return true;
    }

    /**
     * Apply one group: build nested chain items, validate parent compatibility,
     * and return update/insertion instructions. Returns null when the group
     * is skipped (unsupported structure, non-FluentRule entries, type mismatch).
     *
     * @param  array{parent: string, wildcardKeys: array<string, string>, fixedKeys: array<string, string>, wildcardParentKey: ?string}  $group
     * @param  array<string, array{index: int, value: Expr}>  $entries
     * @param  array<string, array{prefix: Expr, suffixKey: string}>  $concatEntries
     * @return array{parentIndex: ?int, parentValue: Expr, insertion: ?array{position: int, item: ArrayItem}, consumed: list<int>}|null
     */
    private function processGroup(array $group, array $entries, array $concatEntries): ?array
    {
        $parentKey = $group['parent'];

        if (! $this->allGroupEntriesAreFluentRule($group, $entries, $parentKey)) {
            $this->logGroupSkip(sprintf(
                "wildcard group has non-FluentRule entries — cannot fold to each() (parent: '%s')",
                $parentKey,
            ));

            return null;
        }

        /** @var list<int> */
        $groupConsumed = [];

        $collected = $this->collectGroupChainItems($group, $entries, $parentKey, $groupConsumed);

        if ($collected === null) {
            return null;
        }

        $eachItems = $collected['eachItems'];
        $childrenItems = $collected['childrenItems'];
        $eachScalar = $collected['eachScalar'];

        $parentValue = isset($entries[$parentKey]) ? $entries[$parentKey]['value'] : null;
        $parentIndex = isset($entries[$parentKey]) ? $entries[$parentKey]['index'] : null;

        if ($parentValue !== null && ! $this->isFluentRuleChain($parentValue)) {
            // Defensive — `allGroupEntriesAreFluentRule` above already
            // rejects this case. Keep the guard but no log entry: any
            // emit here would be unreachable noise in the dedup cache.
            return null;
        }

        if (! $this->parentFactoryAllowsChain($parentValue, $eachItems, $childrenItems, $eachScalar)) {
            $factory = $parentValue instanceof Expr ? ($this->getFluentRuleFactory($parentValue) ?? 'unknown') : 'unknown';
            $this->logGroupSkip(sprintf(
                "parent factory %s() doesn't support each()/children() — only array() and field() do (parent: '%s')",
                $factory,
                $parentKey,
            ));

            return null;
        }

        if ($parentValue === null) {
            // Synthesize a bare FluentRule::array() parent without a presence
            // modifier. Adding ->nullable() here short-circuits Laravel's
            // validation when the parent key is missing, so nested ->required()
            // children would silently never fire. Leaving the synthesized parent
            // bare preserves the original dot-notation semantics: nested
            // `required` children still trigger when the parent is absent.
            $parentValue = $this->buildFluentRuleFactoryCall('array');
        }

        $parentValue = $this->appendChainToParent($parentValue, $eachItems, $childrenItems, $eachScalar);

        $insertion = null;

        if ($parentIndex === null) {
            $insertAt = $groupConsumed !== [] ? min($groupConsumed) : 0;
            $parentKeyExpr = $this->buildParentKeyExpr($parentKey, $concatEntries);
            $insertion = ['position' => $insertAt, 'item' => new ArrayItem($parentValue, $parentKeyExpr)];
        }

        return [
            'parentIndex' => $parentIndex,
            'parentValue' => $parentValue,
            'insertion' => $insertion,
            'consumed' => $groupConsumed,
        ];
    }

    /**
     * Build the each() / children() / scalar-each items for a group, and absorb the
     * wildcard parent entry (items.*) when appropriate. Returns null if nothing
     * would be emitted, or if a non-redundant wildcard parent blocks the group.
     *
     * @param  array{parent: string, wildcardKeys: array<string, string>, fixedKeys: array<string, string>, wildcardParentKey: ?string}  $group
     * @param  array<string, array{index: int, value: Expr}>  $entries
     * @param  list<int>  $groupConsumed  indices to remove (mutated)
     * @return array{eachItems: list<ArrayItem>, childrenItems: list<ArrayItem>, eachScalar: ?Expr}|null
     */
    private function collectGroupChainItems(array $group, array $entries, string $parentKey, array &$groupConsumed): ?array
    {
        $eachItems = $group['wildcardKeys'] !== []
            ? $this->buildNestedItems($group['wildcardKeys'], $entries, $parentKey . '.*', $groupConsumed)
            : [];

        $childrenItems = $group['fixedKeys'] !== []
            ? $this->buildNestedItems($group['fixedKeys'], $entries, $parentKey, $groupConsumed)
            : [];

        // Gap 2: flat-wildcard shorthand. When only a single `items.*`
        // entry exists (no nested `items.*.field` siblings, no explicit
        // `items.*` fixed children), fold the wildcard entry's own
        // FluentRule chain into the parent as `->each(<that chain>)`.
        $eachScalar = null;

        if (
            $eachItems === []
            && $childrenItems === []
            && $group['wildcardParentKey'] !== null
            && isset($entries[$group['wildcardParentKey']])
            && $this->isFluentRuleChain($entries[$group['wildcardParentKey']]['value'])
        ) {
            $eachScalar = $entries[$group['wildcardParentKey']]['value'];
            $groupConsumed[] = $entries[$group['wildcardParentKey']]['index'];
        }

        if ($eachItems === [] && $childrenItems === [] && $eachScalar === null) {
            return null;
        }

        // Absorb the wildcard parent (items.*) only when redundant. When
        // $eachScalar is set we've already consumed it above.
        if ($eachScalar === null && $group['wildcardParentKey'] !== null && isset($entries[$group['wildcardParentKey']])) {
            if ($eachItems !== [] && ! $this->isRedundantWildcardParent($entries[$group['wildcardParentKey']]['value'])) {
                $this->logGroupSkip(sprintf(
                    "wildcard parent '%s' has type-specific rules that would be lost in grouping",
                    $group['wildcardParentKey'],
                ));

                return null;
            }

            $groupConsumed[] = $entries[$group['wildcardParentKey']]['index'];
        }

        return [
            'eachItems' => $eachItems,
            'childrenItems' => $childrenItems,
            'eachScalar' => $eachScalar,
        ];
    }

    /**
     * Validate parent type supports the chain methods we're about to add:
     * each() requires ArrayRule, children() requires ArrayRule or FieldRule.
     *
     * @param  list<ArrayItem>  $eachItems
     * @param  list<ArrayItem>  $childrenItems
     */
    private function parentFactoryAllowsChain(?Expr $parentValue, array $eachItems, array $childrenItems, ?Expr $eachScalar): bool
    {
        if (! $parentValue instanceof Expr) {
            return true;
        }

        $factory = $this->getFluentRuleFactory($parentValue);

        if (($eachItems !== [] || $eachScalar instanceof Expr) && $factory !== 'array') {
            return false;
        }

        return ! ($childrenItems !== [] && ! in_array($factory, ['array', 'field'], true));
    }

    /**
     * @param  list<ArrayItem>  $eachItems
     * @param  list<ArrayItem>  $childrenItems
     */
    private function appendChainToParent(Expr $parentValue, array $eachItems, array $childrenItems, ?Expr $eachScalar): Expr
    {
        if ($eachItems !== []) {
            $parentValue = new MethodCall(
                $parentValue,
                new Identifier('each'),
                [new Arg($this->multilineArray($eachItems))],
            );
        } elseif ($eachScalar instanceof Expr) {
            $parentValue = new MethodCall(
                $parentValue,
                new Identifier('each'),
                [new Arg($eachScalar)],
            );
        }

        if ($childrenItems !== []) {
            return new MethodCall(
                $parentValue,
                new Identifier('children'),
                [new Arg($this->multilineArray($childrenItems))],
            );
        }

        return $parentValue;
    }

    /**
     * Check if a suffix contains wildcards that can't be safely grouped:
     * - `*.*.name` (double wildcard — needs nested each())
     * - `foo.*.bar` with another `*` later (unusual nesting)
     *
     * Grouping only handles suffixes where `*` appears once as the first segment after prefix.
     */
    private function hasInvalidWildcard(string $suffix): bool
    {
        $segments = explode('.', $suffix);
        $wildcardCount = 0;

        foreach ($segments as $segment) {
            if ($segment === '*') {
                ++$wildcardCount;

                if ($wildcardCount > 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if an expression is a FluentRule chain (StaticCall on FluentRule or MethodCall on such).
     * Raw PHP arrays, variables, and other expressions return false.
     */
    private function isFluentRuleChain(Expr $expr): bool
    {
        return $this->getFluentRuleFactory($expr) !== null;
    }

    /**
     * Get the factory method name at the root of a FluentRule chain.
     * Returns 'array', 'field', 'string', etc. or null if not a FluentRule chain.
     *
     * Matches both the fully-qualified class name and the short `FluentRule`
     * name. Sibling rectors (ValidationStringToFluentRuleRector,
     * ValidationArrayToFluentRuleRector) emit the short form when running
     * inside the same set-list pass because their `use` import is queued via
     * the post-rector pipeline and isn't yet present in the tree when this
     * check runs. The short form is authoritative by the time the post-rector
     * pipeline finishes, so matching on either name is safe.
     */
    private function getFluentRuleFactory(Expr $expr): ?string
    {
        $current = $expr;

        while ($current instanceof MethodCall) {
            $current = $current->var;
        }

        if (! $current instanceof StaticCall) {
            return null;
        }

        $className = $this->getName($current->class);

        if ($className !== FluentRule::class && $className !== 'FluentRule') {
            return null;
        }

        return $this->getName($current->name);
    }

    /**
     * Validate that all entries in a group are FluentRule chains.
     * Returns false if any entry is a raw array, variable, etc.
     *
     * @param  array{parent: string, wildcardKeys: array<string, string>, fixedKeys: array<string, string>, wildcardParentKey: ?string}  $group
     * @param  array<string, array{index: int, value: Expr}>  $entries
     */
    private function allGroupEntriesAreFluentRule(array $group, array $entries, string $parentKey): bool
    {
        // Parent (if exists)
        if (isset($entries[$parentKey]) && ! $this->isFluentRuleChain($entries[$parentKey]['value'])) {
            return false;
        }

        // Wildcard parent (items.*)
        if ($group['wildcardParentKey'] !== null && isset($entries[$group['wildcardParentKey']]) && ! $this->isFluentRuleChain($entries[$group['wildcardParentKey']]['value'])) {
            return false;
        }

        // All wildcard children
        foreach (array_keys($group['wildcardKeys']) as $childKey) {
            if (isset($entries[$childKey]) && ! $this->isFluentRuleChain($entries[$childKey]['value'])) {
                return false;
            }
        }

        // All fixed children
        foreach (array_keys($group['fixedKeys']) as $childKey) {
            if (isset($entries[$childKey]) && ! $this->isFluentRuleChain($entries[$childKey]['value'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a wildcard parent value is redundant (only presence modifiers, no type-specific rules).
     * Redundant: FluentRule::field()->required(), FluentRule::field()->nullable()->bail()
     * Non-redundant: FluentRule::field()->required()->min(2) — has min() constraint
     */
    private function isRedundantWildcardParent(Expr $value): bool
    {
        // Walk the method chain and check all method names
        $current = $value;
        $redundantMethods = ['required', 'nullable', 'sometimes', 'filled', 'present', 'missing', 'bail', 'exclude'];

        while ($current instanceof MethodCall) {
            $methodName = $this->getName($current->name);

            if ($methodName === null || ! in_array($methodName, $redundantMethods, true)) {
                return false; // Non-redundant method found
            }

            $current = $current->var;
        }

        // The root should be a FluentRule::field() or FluentRule::array() call
        return $current instanceof StaticCall;
    }

    /**
     * Build a `FluentRule::<factory>()` static call using the short `FluentRule`
     * name. Marks the enclosing namespace as needing a `use SanderMuller\
     * FluentValidation\FluentRule;` import so the short reference resolves
     * correctly when the namespace doesn't already import it.
     */
    private function buildFluentRuleFactoryCall(string $factory): StaticCall
    {
        $this->needsFluentRuleImport = true;

        return new StaticCall(
            new Name('FluentRule'),
            new Identifier($factory),
        );
    }

    /**
     * Build an Array_ node flagged for multi-line printing.
     *
     * Rector's BetterStandardPrinter honours NEWLINED_ARRAY_PRINT to force one
     * item per line. Without it, synthesized children()/each() arrays collapse
     * onto a single line because nikic/php-parser only breaks arrays whose
     * items carry comments, which Pint can't undo when nested expressions
     * (e.g. ->in([...])) keep the outer line short enough to survive wrapping.
     *
     * @param  list<ArrayItem>  $items
     */
    private function multilineArray(array $items): Array_
    {
        $array = new Array_($items);
        $array->setAttribute(AttributeKey::NEWLINED_ARRAY_PRINT, true);

        return $array;
    }

    /**
     * Build the key expression for a new parent entry.
     * For concat groups, uses the prefix expression. For string groups, uses a String_ node.
     *
     * @param  array<string, array{prefix: Expr, suffixKey: string}>  $concatEntries
     */
    private function buildParentKeyExpr(string $parentKey, array $concatEntries): Expr
    {
        // Check if any child in concatEntries has this prefix
        foreach ($concatEntries as $entryKey => $meta) {
            if (str_starts_with($entryKey, $parentKey . '.')) {
                return $meta['prefix'];
            }
        }

        return new String_($parentKey);
    }
}
