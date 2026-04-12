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
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Return_;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEachRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Groups flat wildcard/dotted validation keys into nested each()/children() calls.
 *
 * Before: 'items' => ..., 'items.*.name' => ..., 'items.*.email' => ...
 * After:  'items' => FluentRule::array()->required()->each(['name' => ..., 'email' => ...])
 *
 * Skips Livewire classes (each() nesting breaks Livewire's wildcard handling).
 *
 * @see GroupWildcardRulesToEachRectorTest
 */
final class GroupWildcardRulesToEachRector extends AbstractRector implements DocumentedRuleInterface
{
    use LogsSkipReasons;

    private const int MAX_NESTING_DEPTH = 4;

    /** @var list<string> */
    private const array LIVEWIRE_CLASSES = [
        'Livewire\Component',
        'Livewire\Form',
    ];

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
        return [ClassLike::class];
    }

    /**
     * Map of local class constant name → string value.
     * Built from the class's own constants for resolving `self::X` keys.
     *
     * @var array<string, string>
     */
    private array $localConstants = [];

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        if ($this->isLivewireClass($node)) {
            $this->logSkip($node, 'detected as Livewire (nested each() breaks Livewire wildcard handling; trait added separately)');

            return null;
        }

        // Build map of local string constants for resolving self::X keys
        $this->localConstants = $this->collectLocalStringConstants($node);

        $hasChanged = false;

        foreach ($node->getMethods() as $method) {
            if (! $this->isName($method, 'rules')) {
                continue;
            }

            $this->traverseNodesWithCallable($method, function (Node $inner) use (&$hasChanged): ?Return_ {
                if (! $inner instanceof Return_ || ! $inner->expr instanceof Array_) {
                    return null;
                }

                if ($this->groupRulesArray($inner->expr)) {
                    $hasChanged = true;

                    return $inner;
                }

                return null;
            });
        }

        return $hasChanged ? $node : null;
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

    // ─── Livewire guard ──────────────────────────────────────────────────

    private function isLivewireClass(Class_ $class): bool
    {
        if ($class->extends instanceof Name) {
            $parentName = $this->getName($class->extends);

            if (in_array($parentName, self::LIVEWIRE_CLASSES, true)) {
                return true;
            }
        }

        // Check if the class uses HasFluentValidation trait (Livewire trait)
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                if ($this->getName($trait) === HasFluentValidation::class) {
                    return true;
                }
            }
        }

        // Heuristic: classes with a render() method are Livewire components/forms,
        // even if they extend an intermediate base class. Matches the detection
        // used in AddHasFluentValidationTraitRector so indirect Livewire subclasses
        // aren't silently grouped without the matching trait.
        foreach ($class->getMethods() as $method) {
            if ($this->isName($method, 'render')) {
                return true;
            }
        }

        return false;
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

        foreach ($array->items as $index => $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($item->key instanceof String_) {
                $entries[$item->key->value] = ['index' => $index, 'value' => $item->value];
            } elseif ($item->key instanceof ClassConstFetch) {
                // Try to resolve to string value: self::ITEMS where ITEMS = 'interactions'
                $resolvedKey = $this->resolveClassConstToString($item->key);

                if ($resolvedKey !== null) {
                    $entries[$resolvedKey] = ['index' => $index, 'value' => $item->value];
                    // Remember this ClassConstFetch as the preferred key expression for this path
                    $concatEntries[$resolvedKey] = [
                        'prefix' => $item->key,
                        'suffixKey' => '',
                    ];
                } else {
                    // Fallback: synthetic key for constants we can't resolve
                    $syntheticKey = $this->classConstToSyntheticKey($item->key);

                    if ($syntheticKey !== null) {
                        $entries[$syntheticKey] = ['index' => $index, 'value' => $item->value];
                        $concatEntries[$syntheticKey] = [
                            'prefix' => $item->key,
                            'suffixKey' => '',
                        ];
                    }
                }
            } elseif ($item->key instanceof Concat) {
                $parsed = $this->parseConcatKey($item->key);

                if ($parsed !== null) {
                    // Try to fully resolve concat key if prefix is a known constant
                    $resolved = $this->resolveConcatToString($parsed);

                    if ($resolved !== null) {
                        $entries[$resolved] = ['index' => $index, 'value' => $item->value];
                        $concatEntries[$resolved] = [
                            'prefix' => $parsed['prefixExpr'],
                            'suffixKey' => $parsed['suffix'],
                        ];
                    } else {
                        $syntheticKey = $parsed['prefixId'] . $parsed['suffix'];
                        $entries[$syntheticKey] = ['index' => $index, 'value' => $item->value];
                        $concatEntries[$syntheticKey] = [
                            'prefix' => $parsed['prefixExpr'],
                            'suffixKey' => $parsed['suffix'],
                        ];
                    }
                }
            }
        }

        if ($entries === []) {
            return false;
        }

        $groups = $this->findTopLevelGroups($entries);

        if ($groups === []) {
            return false;
        }

        return $this->applyGroups($array, $groups, $entries, $concatEntries);
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
     * @param  array{prefixExpr: Expr, prefix: string, suffix: string, prefixId: string}  $parsed
     */
    private function resolveConcatToString(array $parsed): ?string
    {
        if (! $parsed['prefixExpr'] instanceof ClassConstFetch) {
            return null;
        }

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
     * Parse a Concat key expression into a prefix expression + string suffix.
     *
     * Example: `self::INTERACTIONS . '.*.type'`
     * → prefixExpr: ClassConstFetch(self, INTERACTIONS)
     * → suffix: '.*.type'
     * → prefixId: '__concat_self::INTERACTIONS__'
     *
     * @return array{prefixExpr: Expr, prefix: string, suffix: string, prefixId: string}|null
     */
    private function parseConcatKey(Concat $concat): ?array
    {
        // Flatten the concat chain into parts
        $parts = $this->flattenConcat($concat);

        // Find the split point: first non-expression part with a dot
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
            'prefixExpr' => $prefixExpr,
            'prefix' => $prefixId,
            'suffix' => $suffix,
            'prefixId' => $prefixId,
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
                continue; // Skip this group entirely
            }

            if ($wildcardKeys !== [] || $fixedKeys !== []) {
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

        // Group by first child segment
        /** @var array<string, list<string>> */
        $segmentToKeys = [];

        foreach ($keyToSegment as $fullKey => $segment) {
            $segmentToKeys[$segment][] = $fullKey;
        }

        $items = [];

        foreach ($segmentToKeys as $segment => $keys) {
            if (count($keys) === 1 && $keys[0] === $prefix . '.' . $segment) {
                // Leaf: exact match, no deeper nesting
                $items[] = new ArrayItem($entries[$keys[0]]['value'], new String_($segment));
                $consumed[] = $entries[$keys[0]]['index'];
            } else {
                // This segment has sub-children — recurse
                $childValue = null;
                $directKey = $prefix . '.' . $segment;

                if (isset($entries[$directKey])) {
                    $childValue = $entries[$directKey]['value'];
                    $consumed[] = $entries[$directKey]['index'];
                }

                // Collect sub-children
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
                        $subSegment = explode('.', substr($subSuffix, 2))[0];
                        $subWildcard[$fullKey] = $subSegment;
                    } else {
                        $subSegment = explode('.', $subSuffix)[0];
                        $subFixed[$fullKey] = $subSegment;
                    }
                }

                if ($childValue === null) {
                    $childValue = new StaticCall(
                        new FullyQualified(FluentRule::class),
                        new Identifier('field'),
                    );
                }

                // Absorb wildcard parent
                if ($subWildcardParent !== null && isset($entries[$subWildcardParent])) {
                    $consumed[] = $entries[$subWildcardParent]['index'];
                }

                // Recurse for wildcard sub-children
                if ($subWildcard !== []) {
                    $eachItems = $this->buildNestedItems($subWildcard, $entries, $directKey . '.*', $consumed, $depth + 1);

                    if ($eachItems !== []) {
                        $childValue = new MethodCall($childValue, new Identifier('each'), [
                            new Arg(new Array_($eachItems)),
                        ]);
                    }
                }

                // Recurse for fixed sub-children
                if ($subFixed !== []) {
                    $childrenItems = $this->buildNestedItems($subFixed, $entries, $directKey, $consumed, $depth + 1);

                    if ($childrenItems !== []) {
                        $childValue = new MethodCall($childValue, new Identifier('children'), [
                            new Arg(new Array_($childrenItems)),
                        ]);
                    }
                }

                $items[] = new ArrayItem($childValue, new String_($segment));
            }
        }

        return $items;
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
            $parentKey = $group['parent'];

            // Pre-validate: ALL entries in this group (parent + wildcard parent + children)
            // must be FluentRule chains. Otherwise the nested ArrayItems would contain
            // raw PHP arrays that can't be used as ValidationRule values.
            if (! $this->allGroupEntriesAreFluentRule($group, $entries, $parentKey)) {
                continue;
            }

            /** @var list<int> */
            $groupConsumed = [];

            // Build nested each() items (recursive)
            $eachItems = [];

            if ($group['wildcardKeys'] !== []) {
                $eachItems = $this->buildNestedItems(
                    $group['wildcardKeys'],
                    $entries,
                    $parentKey . '.*',
                    $groupConsumed,
                );
            }

            // Build nested children() items (recursive)
            $childrenItems = [];

            if ($group['fixedKeys'] !== []) {
                $childrenItems = $this->buildNestedItems(
                    $group['fixedKeys'],
                    $entries,
                    $parentKey,
                    $groupConsumed,
                );
            }

            if ($eachItems === [] && $childrenItems === []) {
                continue;
            }

            // Check wildcard parent (items.*) — only absorb if redundant
            if ($group['wildcardParentKey'] !== null && isset($entries[$group['wildcardParentKey']])) {
                if ($eachItems !== [] && ! $this->isRedundantWildcardParent($entries[$group['wildcardParentKey']]['value'])) {
                    continue; // Non-redundant → bail on this group
                }

                $groupConsumed[] = $entries[$group['wildcardParentKey']]['index'];
            }

            // Get or create parent value
            $parentValue = isset($entries[$parentKey]) ? $entries[$parentKey]['value'] : null;
            $parentIndex = isset($entries[$parentKey]) ? $entries[$parentKey]['index'] : null;

            // If parent exists but isn't a FluentRule chain (e.g., raw array literal), bail
            if ($parentValue !== null && ! $this->isFluentRuleChain($parentValue)) {
                continue;
            }

            // Validate parent type supports the chain methods we're about to add
            // - each() only on ArrayRule (FluentRule::array())
            // - children() on ArrayRule or FieldRule (FluentRule::array() or ::field())
            if ($parentValue !== null) {
                $factory = $this->getFluentRuleFactory($parentValue);

                if ($eachItems !== [] && $factory !== 'array') {
                    continue; // each() requires ArrayRule
                }

                if ($childrenItems !== [] && ! in_array($factory, ['array', 'field'], true)) {
                    continue; // children() requires ArrayRule or FieldRule
                }
            }

            if ($parentValue === null) {
                // Synthesize a nullable array parent to match Laravel's flat-rule
                // behavior: `items.*.x => required` without an `items` rule passes
                // when `items` is null or missing. Scalar inputs still fail with
                // `validation.array`, which is an intentional improvement — a
                // scalar parent means zero wildcard iterations, which is almost
                // always a latent bug in the caller.
                $parentValue = new MethodCall(
                    new StaticCall(
                        new FullyQualified(FluentRule::class),
                        new Identifier('array'),
                    ),
                    new Identifier('nullable'),
                );
            }

            if ($eachItems !== []) {
                $parentValue = new MethodCall(
                    $parentValue,
                    new Identifier('each'),
                    [new Arg(new Array_($eachItems))],
                );
            }

            if ($childrenItems !== []) {
                $parentValue = new MethodCall(
                    $parentValue,
                    new Identifier('children'),
                    [new Arg(new Array_($childrenItems))],
                );
            }

            if ($parentIndex !== null) {
                $indicesToUpdate[$parentIndex] = $parentValue;
            } else {
                $insertAt = $groupConsumed !== [] ? min($groupConsumed) : 0;
                $parentKeyExpr = $this->buildParentKeyExpr($parentKey, $concatEntries);
                $insertions[] = ['position' => $insertAt, 'item' => new ArrayItem($parentValue, $parentKeyExpr)];
            }

            $indicesToRemove = [...$indicesToRemove, ...$groupConsumed];
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

        if ($this->getName($current->class) !== FluentRule::class) {
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
