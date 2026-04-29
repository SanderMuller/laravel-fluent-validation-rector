<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PostRector\Collector\UseNodesToAddCollector;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidation\HasFluentValidationForFilament;
use SanderMuller\FluentValidationRector\Internal\RunSummary;
use SanderMuller\FluentValidationRector\Rector\Concerns\ConvertsValidationRuleArrays;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsLivewireRuleAttributes;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsRulesShapedMethods;
use SanderMuller\FluentValidationRector\Rector\Concerns\ExpandsKeyedAttributeArrays;
use SanderMuller\FluentValidationRector\Rector\Concerns\ExtractsExplicitValidateKeys;
use SanderMuller\FluentValidationRector\Rector\Concerns\ExtractsLivewireAttributeLabels;
use SanderMuller\FluentValidationRector\Rector\Concerns\IdentifiesLivewireClasses;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\MigratesAttributeMessages;
use SanderMuller\FluentValidationRector\Rector\Concerns\PredictsLivewireAttributeEmitKeys;
use SanderMuller\FluentValidationRector\Rector\Concerns\QualifiesForRulesProcessing;
use SanderMuller\FluentValidationRector\Rector\Concerns\ReportsLivewireAttributeArgs;
use SanderMuller\FluentValidationRector\Rector\Concerns\ResolvesInheritedRulesVisibility;
use SanderMuller\FluentValidationRector\Rector\Concerns\ResolvesRealtimeValidationMarker;
use SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\ConvertLivewireRuleAttributeRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Converts Livewire's `#[Rule(...)]` / `#[Validate(...)]` attribute-based
 * validation to a FluentRule builder via a generated `rules(): array`
 * method.
 *
 * Livewire's attribute arguments must be const expressions, which
 * prohibits expressing FluentRule chains, closures, or custom rule
 * objects. `rules(): array` with `HasFluentValidation` is the idiomatic
 * shape that supports the full FluentRule API, so the migration strips
 * the attribute and generates the method body.
 *
 *     // Before
 *     class Settings extends Component
 *     {
 *         #[Rule('nullable|email', as: 'email address')]
 *         public ?string $email = '';
 *     }
 *
 *     // After
 *     class Settings extends Component
 *     {
 *         public ?string $email = '';
 *
 *         protected function rules(): array
 *         {
 *             return [
 *                 'email' => FluentRule::email()->nullable()->label('email address'),
 *             ];
 *         }
 *     }
 *
 * `HasFluentValidation` is added by the companion `AddHasFluentValidationTraitRector`
 * when the set list runs this rule before the trait-insertion step.
 *
 * @see ConvertLivewireRuleAttributeRectorTest
 */
final class ConvertLivewireRuleAttributeRector extends AbstractRector implements ConfigurableRectorInterface, DocumentedRuleInterface
{
    public const string PRESERVE_REALTIME_VALIDATION = 'preserve_realtime_validation';

    /**
     * Config key for the key-overlap behavior. Controls how the rector
     * reacts when a class has `#[Validate]` attrs AND at least one
     * `$this->validate([...])` / `$this->validateOnly($field, [...])` call
     * with an explicit-array rules-arg.
     *
     * See spec `livewire-attribute-overlap-config.md` for the mode matrix
     * and safety analysis.
     */
    public const string KEY_OVERLAP_BEHAVIOR = 'key_overlap_behavior';

    public const string OVERLAP_BEHAVIOR_BAIL = 'bail';

    public const string OVERLAP_BEHAVIOR_PARTIAL = 'partial';

    /**
     * Opt-in flag controlling `message:` attribute-arg migration into a
     * generated `messages(): array` method. Default `false` (legacy
     * behavior — `message:` args are skip-logged and the user migrates
     * manually). When `true`, the rector collects `message:` values per
     * property and installs a `messages()` method alongside `rules()`.
     *
     * Opt-in rather than default-on because `messages()` generation
     * expands the class surface (a second framework-called method) and
     * some consumers centralize messages in `lang/en/validation.php`
     * files where the rector's inline `messages()` output would be
     * unwanted duplication.
     */
    public const string MIGRATE_MESSAGES = 'migrate_messages';

    private bool $migrateMessages = false;

    private string $keyOverlapBehavior = self::OVERLAP_BEHAVIOR_BAIL;

    /**
     * Property names whose `#[Validate]` attrs overlap with at least one
     * explicit `$this->validate([...])` key on the current class under
     * processing. Populated by `shouldProcessClass` when
     * `key_overlap_behavior = 'partial'` and consulted by `collectRuleEntries`
     * to leave matching properties' attrs intact.
     *
     * @var array<string, true>
     */
    private array $partialOverlapSkipKeys = [];

    /**
     * Static dedup map for the 1.2.0 layer-2 compose-conflict warning.
     * Keyed on class FQCN; same class doesn't generate duplicate
     * skip-log entries as the rector visits it. Mirrors
     * `QualifiesForRulesProcessing::$denylistedAttributedWarnings`.
     *
     * @var array<string, true>
     */
    private static array $composeConflictWarnings = [];

    use ConvertsValidationRuleArrays;
    use DetectsInheritedTraits;
    use DetectsLivewireRuleAttributes;
    use DetectsRulesShapedMethods;
    use ExpandsKeyedAttributeArrays;
    use ExtractsExplicitValidateKeys;
    use ExtractsLivewireAttributeLabels;
    use IdentifiesLivewireClasses;
    use LogsSkipReasons;
    use MigratesAttributeMessages;
    use PredictsLivewireAttributeEmitKeys;
    use QualifiesForRulesProcessing;
    use ReportsLivewireAttributeArgs;
    use ResolvesInheritedRulesVisibility;
    use ResolvesRealtimeValidationMarker;

    public function __construct(private readonly UseNodesToAddCollector $useNodesToAddCollector)
    {
        RunSummary::registerShutdownHandler();
    }

    public function configure(array $configuration): void
    {
        $value = $configuration[self::PRESERVE_REALTIME_VALIDATION] ?? null;

        if (is_bool($value)) {
            $this->preserveRealtimeValidation = $value;
        }

        $messagesFlag = $configuration[self::MIGRATE_MESSAGES] ?? null;

        if (is_bool($messagesFlag)) {
            $this->migrateMessages = $messagesFlag;
        }

        $overlapMode = $configuration[self::KEY_OVERLAP_BEHAVIOR] ?? null;

        if (is_string($overlapMode) && in_array($overlapMode, [
            self::OVERLAP_BEHAVIOR_BAIL,
            self::OVERLAP_BEHAVIOR_PARTIAL,
        ], true)) {
            $this->keyOverlapBehavior = $overlapMode;
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Convert Livewire's #[Rule(...)] attribute validation to a rules(): array method using FluentRule.",
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Livewire\Attributes\Rule;
use Livewire\Component;

class Settings extends Component
{
    #[Rule('nullable|email', as: 'email address')]
    public ?string $email = '';
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

class Settings extends Component
{
    public ?string $email = '';

    protected function rules(): array
    {
        return [
            'email' => FluentRule::email()->nullable()->label('email address'),
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

        if (! $this->shouldProcessClass($node)) {
            return null;
        }

        // Collect message entries BEFORE collectRuleEntries — the
        // latter strips attributes via extractAndStripRuleAttribute,
        // which would leave the message walk with nothing to read.
        // The walk reads attributes without mutation; the
        // installMessagesMethod call below is gated on $migrateMessages
        // (returns [] when off, so no-op on the install).
        $messageEntries = $this->collectMessageEntries($node);

        // Preflight: if migration produced entries but the install would
        // fail (existing non-trivial messages() body, etc.), bail the
        // entire conversion. Stripping attributes + failed messages()
        // install would lose the user's message: data silently. Logging
        // here keeps the source intact.
        if ($messageEntries !== [] && ! $this->canInstallMessagesMethod($node)) {
            $this->logSkip($node, sprintf(
                'migrate_messages: %d migrated message %s cannot be installed (existing messages() method is not a simple `return [...]`); leaving #[Validate] attributes intact',
                count($messageEntries),
                count($messageEntries) === 1 ? 'entry' : 'entries',
            ));

            return null;
        }

        $collected = $this->collectRuleEntries($node);

        if ($collected === []) {
            return null;
        }

        if (! $this->installRulesMethod($node, $collected)) {
            return null;
        }

        $this->installMessagesMethod($node, $messageEntries);

        $this->queueFluentRuleImport();

        return $node;
    }

    /**
     * Walk every Property on the class, extract its rule-attribute entries,
     * and expand them into one `{name, expr}` pair per property-item per
     * entry. `null` names from a default single-chain extraction fall back
     * to the annotated property's own name; explicit keyed-array names
     * (`#[Validate(['todos' => …, 'todos.*' => …])]`) are kept verbatim.
     *
     * @return list<array{name: string, expr: Expr}>
     */
    private function collectRuleEntries(Class_ $class): array
    {
        $collected = [];

        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }

            // Partial-overlap mode: properties whose name appears in an
            // explicit `$this->validate([...])` key set stay as attrs (no
            // extract, no strip). The existing validate() call continues to
            // drive that property's validation; stripping the attr would
            // remove the runtime real-time-validation source leaving only
            // the action-method check, changing behavior. Property names
            // that don't overlap convert normally.
            if ($this->propertyHasPartialOverlap($stmt)) {
                continue;
            }

            $entries = $this->extractAndStripRuleAttribute($stmt, $class);

            if ($entries === null) {
                continue;
            }

            foreach ($stmt->props as $propertyItem) {
                $propertyName = $propertyItem->name->toString();

                foreach ($entries as $entry) {
                    $collected[] = [
                        'name' => $entry['name'] ?? $propertyName,
                        'expr' => $this->cloneExpr($entry['expr']),
                    ];
                }
            }
        }

        return $collected;
    }

    /**
     * Gate the class through three pre-processing checks:
     *
     * 1. **Attribute presence.** Silent no-op on classes without a
     *    `#[Rule]` / `#[Validate]` attribute — nothing for this rector to do.
     *    Pre-0.4.17 the hybrid-bail fired on any class with a `$this->validate(…)`
     *    call regardless of attribute presence, producing dozens of spurious
     *    skip-log entries on Actions / FormRequests / Controllers /
     *    DataObjects with unrelated `validate()` calls.
     * 2. **Hybrid classes.** Attributes AND explicit `$this->validate([…])`
     *    with a rules-array arg means the explicit call is the source of
     *    truth; converting the attributes would produce dead-code `rules()`.
     *    Skip-log.
     * 3. **Final ancestor `rules()`.** Resolved BEFORE mutating properties —
     *    when a parent class declares `final public function rules(): array`,
     *    the generated method can't be installed. Bail early to avoid
     *    stripping attributes we then can't replace.
     */
    private function shouldProcessClass(Class_ $class): bool
    {
        $this->partialOverlapSkipKeys = [];

        if (! $this->hasAnyLivewireRuleAttribute($class)) {
            return false;
        }

        // 1.2.0 layer-2 compose-conflict warning. Class with Livewire
        // `#[Rule]` / `#[Validate]` property attribute AND
        // `HasFluentValidation` (or its Filament variant) trait use:
        // the trait's `getRules()` returns the trait-side `rules()`
        // method's output and does NOT consult the Livewire attribute
        // pathway, so `validateOnly($prop)` for an attribute-bound
        // property silently returns `[]`. Diagnostic only — no
        // auto-rewrite (the right fix depends on consumer intent:
        // move rule into trait's `rules()` OR remove the trait).
        // Bails conversion: rector-side conversion would generate a
        // child `rules()` method that overrides the parent's,
        // potentially losing parent-owned fields — wrong fix shape
        // for this composition.
        if ($this->warnLivewireFluentTraitComposeConflict($class)) {
            return false;
        }

        $explicit = $this->extractExplicitValidateKeys($class);

        // `'unsafe'` => at least one `$this->validate()` call with a
        // shape we can't statically inspect (variable, array_merge, etc.).
        // The overlap set would be incomplete; revert to the classwide bail
        // regardless of configured mode. Codex review requirement.
        if ($explicit === 'unsafe') {
            $this->logSkip($class, '$this->validate() called with extraction-unsafe arg shape — classwide bail regardless of key_overlap_behavior config');

            return false;
        }

        if (is_array($explicit) && $explicit !== []) {
            if ($this->keyOverlapBehavior === self::OVERLAP_BEHAVIOR_BAIL) {
                // 1.2.1: per-attribute pinpoint emit (was a single classwide
                // skip pre-1.2.1). Each Livewire-attributed property gets its
                // own skip-log entry naming whether its predicted emit keys
                // overlap the explicit `$this->validate([...])` keys —
                // consumers with multiple attributed properties no longer
                // have to manually correlate the classwide bail to specific
                // attrs. Cardinality fix; bail outcome unchanged.
                $this->emitOverlapBailPerProperty($class, $explicit);

                return false;
            }

            // Partial mode: stash the overlap set so collectRuleEntries
            // can leave matching properties' attrs in place.
            $this->partialOverlapSkipKeys = array_fill_keys($explicit, true);
        }

        if ($this->resolveGeneratedRulesVisibility($class) === null) {
            $this->logSkip($class, 'parent class declares final rules() method; cannot override — skipping to avoid fatal-on-load');

            return false;
        }

        return true;
    }

    /**
     * 1.2.0 layer-2 compose-conflict warning. Returns true (and emits
     * a per-property skip-log entry) when:
     *
     *  - The class has at least one Livewire `#[Rule]` / `#[Validate]`
     *    attribute on a property; AND
     *  - An ANCESTOR (not the class itself) uses `HasFluentValidation`
     *    or `HasFluentValidationForFilament`.
     *
     * The narrowing to ancestor-only is intentional: only the inherited
     * shape carries unfixable conversion risk. The trait's `getRules()`
     * reads `$this->rules()` — when an ancestor declares the trait AND
     * also declares `rules(): array`, generating a child `rules()`
     * overrides the parent's and silently drops parent-owned fields
     * (and the pre-conversion runtime already silently ignored the
     * `#[Rule]` attribute, so the user has no working baseline either).
     *
     * Direct-trait use on the class itself is NOT flagged here:
     *
     *  - Class with local `rules()` method: the rector merges the
     *    attribute rule into that existing array — no clobber, no
     *    silent-ignore post-convert.
     *  - Class with no local `rules()` method: the rector installs one
     *    holding the attribute rule — runtime now resolves it through
     *    the trait's `getRules()`, fixing the silent-ignore.
     *
     * Either way the direct-trait shape converts cleanly; bailing on
     * it would block a working transformation. (Codex review on the
     * 1.2.0 candidate flagged the original "current-or-ancestor"
     * detection as overbroad for exactly this reason.)
     *
     * Static-deduped by class FQCN so the same class doesn't emit
     * duplicate warnings on re-visits. Mirrors the intra-package
     * `warnDenylistedAttributedMethods` pattern in
     * `QualifiesForRulesProcessing`.
     *
     * Origin: surfaced post-1.1.0 from a real consumer 2FA-flow
     * production bug where `validateOnly('phoneNumber')` returned `[]`
     * for an attribute-bound property on a class whose abstract parent
     * used `HasFluentValidation`. Codex pre-tag review on the 1.2.0
     * candidate narrowed the original `currentOrAncestorUsesTrait`
     * check to ancestor-only (`aliasAwareAncestorUsesTrait`) — the
     * direct-trait shape converts cleanly via attribute-into-rules()
     * merge and was being over-bailed.
     */
    private function warnLivewireFluentTraitComposeConflict(Class_ $class): bool
    {
        $fqcn = $this->getName($class);

        if ($fqcn === null || ! $this->ancestorUsesFluentValidationTrait($class)) {
            return false;
        }

        $alreadyEmitted = isset(self::$composeConflictWarnings[$fqcn]);
        $hadConflict = false;

        foreach ($class->stmts as $stmt) {
            if (! ($stmt instanceof Property)) {
                continue;
            }

            if (! $this->propertyHasLivewireRuleAttribute($stmt)) {
                continue;
            }

            $hadConflict = true;

            if ($alreadyEmitted) {
                continue;
            }

            $this->emitComposeConflictForProperty($class, $stmt);
        }

        if ($hadConflict) {
            self::$composeConflictWarnings[$fqcn] = true;
        }

        return $hadConflict;
    }

    /**
     * True when an ANCESTOR of `$class` (not the class itself) uses
     * `HasFluentValidation` or `HasFluentValidationForFilament`. Direct
     * trait use on `$class` returns false here so the convertible
     * direct-trait shape is not bailed.
     */
    private function ancestorUsesFluentValidationTrait(Class_ $class): bool
    {
        if ($this->aliasAwareAncestorUsesTrait($class, HasFluentValidation::class)) {
            return true;
        }

        return $this->aliasAwareAncestorUsesTrait($class, HasFluentValidationForFilament::class);
    }

    /**
     * Per-property emit for the compose-conflict warning. Each property
     * in a multi-prop declaration (`public string $a, $b;`) gets its
     * own skip-log entry naming the property explicitly.
     */
    private function emitComposeConflictForProperty(Class_ $class, Property $property): void
    {
        foreach ($property->props as $propertyItem) {
            $this->logSkip(
                $class,
                sprintf(
                    'property `$%s` carries `#[Livewire\\Attributes\\Rule]` (or `#[Validate]`) but an ancestor class uses `HasFluentValidation` trait — the attribute is silently ignored at runtime (`validateOnly()` returns `[]` for this field) and rector-side conversion would override the parent\'s `rules()` method, dropping parent-owned fields. Either move the rule into the parent\'s `rules()` method, or remove the trait from the parent if Livewire-attribute validation is intended.',
                    $propertyItem->name->toString(),
                ),
            );
        }
    }

    /**
     * Per-property overlap-bail emit (1.2.1, replacing the pre-1.2.1
     * classwide single emit). For each property carrying Livewire
     * `#[Rule]` / `#[Validate]` attributes, predict the rule keys those
     * attributes would emit (`predictEmitKeysForProperty`) and compare
     * against the explicit `$this->validate([...])` key set. Emit one
     * skip-log entry per property naming whether its key(s) overlap.
     *
     * Non-overlapping properties are still bailed (default mode is
     * classwide-conservative) but the message tells the consumer that
     * `KEY_OVERLAP_BEHAVIOR=partial` would convert that specific
     * property — the bail is only forced for the genuinely-overlapping
     * ones. Overlapping properties get a different message indicating
     * the overlap would force preservation under partial mode too.
     *
     * @param  list<string>  $explicit  Static-extracted keys from the class's
     *                                   `$this->validate([...])` call(s).
     */
    private function emitOverlapBailPerProperty(Class_ $class, array $explicit): void
    {
        $explicitDisplay = $this->formatKeyList($explicit);
        $explicitSet = array_fill_keys($explicit, true);

        foreach ($class->stmts as $stmt) {
            if (! ($stmt instanceof Property)) {
                continue;
            }

            if (! $this->propertyHasLivewireRuleAttribute($stmt)) {
                continue;
            }

            $emitKeys = $this->predictEmitKeysForProperty($stmt);
            $overlapping = array_values(array_filter(
                $emitKeys,
                static fn (string $key): bool => isset($explicitSet[$key]),
            ));

            foreach ($stmt->props as $propertyItem) {
                $this->logSkip(
                    $class,
                    $this->formatOverlapBailMessage(
                        $propertyItem->name->toString(),
                        $emitKeys,
                        $overlapping,
                        $explicitDisplay,
                    ),
                    location: $stmt,
                );
            }
        }
    }

    /**
     * @param  list<string>  $emitKeys
     * @param  list<string>  $overlapping
     */
    private function formatOverlapBailMessage(
        string $propertyName,
        array $emitKeys,
        array $overlapping,
        string $explicitDisplay,
    ): string {
        $emitDisplay = $this->formatKeyList($emitKeys);

        if ($overlapping !== []) {
            return sprintf(
                'property `$%s` Livewire attribute key(s) %s overlap explicit `$this->validate(...)` key(s) %s — KEY_OVERLAP_BEHAVIOR=bail (default) keeps it as attrs; under KEY_OVERLAP_BEHAVIOR=partial the overlap still forces preservation (overlap = explicit-call wins).',
                $propertyName,
                $this->formatKeyList($overlapping),
                $explicitDisplay,
            );
        }

        return sprintf(
            'property `$%s` Livewire attribute key(s) %s do NOT overlap explicit `$this->validate(...)` key(s) %s — KEY_OVERLAP_BEHAVIOR=bail (default) skips classwide regardless. Set KEY_OVERLAP_BEHAVIOR=partial to convert this property → see PUBLIC_API.md#convertlivewireruleattributerector for canonical wiring.',
            $propertyName,
            $emitDisplay,
            $explicitDisplay,
        );
    }

    /**
     * @param  list<string>  $keys
     */
    private function formatKeyList(array $keys): string
    {
        if ($keys === []) {
            return '[]';
        }

        return '[' . implode(', ', array_map(static fn (string $k): string => "'{$k}'", $keys)) . ']';
    }

    /**
     * Helper for `warnLivewireFluentTraitComposeConflict` — returns true
     * when the property carries any `#[Livewire\Attributes\Rule]` or
     * `#[Validate]` attribute, regardless of convertibility shape. The
     * existing `hasAnyLivewireRuleAttribute` works at class scope; this
     * is the per-property variant.
     */
    private function propertyHasLivewireRuleAttribute(Property $property): bool
    {
        foreach ($property->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isLivewireRuleAttribute($attr)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract the rules-entry list from the property's #[Rule] / #[Validate]
     * attributes and remove those attributes. Each returned entry is a
     * `{name: ?string, expr: Expr}` pair: `name = null` means "use the
     * annotated property's own name" (the default single-chain case), an
     * explicit `name` comes from a keyed-array attribute and is used
     * verbatim as the `rules()` key.
     *
     * Returns null when no attribute matched or the attribute shape isn't
     * convertible — the existing attribute groups are preserved in that
     * case so the consumer can see the untouched input.
     *
     * @return list<array{name: ?string, expr: Expr}>|null
     */
    private function extractAndStripRuleAttribute(Property $property, Class_ $class): ?array
    {
        $allEntries = [];
        $markerName = null;

        // Aggregate opt-out check runs BEFORE the strip loop so a later
        // `#[Validate(onUpdate: false)]` attribute on the same property
        // (past the first-wins extraction point, soon to be stripped) can
        // still veto marker preservation. Without this the first-wins
        // behaviour for rule extraction would silently re-enable real-time
        // validation for a property whose user explicitly turned it off.
        $vetoedByOptOut = $this->anyValidateOptsOutOfRealtime($property);

        foreach ($property->attrGroups as $groupIndex => $attrGroup) {
            $remainingAttrs = [];

            foreach ($attrGroup->attrs as $attr) {
                if (! $this->isLivewireRuleAttribute($attr)) {
                    $remainingAttrs[] = $attr;

                    continue;
                }

                $converted = $this->convertAttributeToEntries($attr, $property, $class);

                if ($converted === null) {
                    $remainingAttrs[] = $attr;

                    continue;
                }

                // First convertible Rule attribute becomes the source; additional
                // attributes on the same property are unusual, so the first one wins.
                if ($allEntries === []) {
                    $allEntries = $converted;
                }

                if ($markerName === null && $this->shouldPreserveRealtimeMarker($attr)) {
                    $markerName = $attr->name;
                }
            }

            if ($remainingAttrs === []) {
                unset($property->attrGroups[$groupIndex]);

                continue;
            }

            $property->attrGroups[$groupIndex] = new AttributeGroup($remainingAttrs);
        }

        $property->attrGroups = array_values($property->attrGroups);

        if (! $vetoedByOptOut) {
            $this->appendRealtimeValidationMarker($property, $markerName);
        }

        return $allEntries === [] ? null : $allEntries;
    }

    /**
     * Convert one `#[Rule(…)]` / `#[Validate(…)]` attribute into a list of
     * `rules()` entries. Three branches:
     *
     * - String first arg — single chain, `name = null` (use property name).
     * - **List-array** first arg (`['required', 'email']`) — single chain,
     *   `name = null`.
     * - **Keyed-array** first arg (`['todos' => 'required', 'todos.*' => …]`) —
     *   one entry per key, each carrying the key as its explicit `name`.
     *
     * The keyed branch is routed whenever any `ArrayItem->key` is a `String_`.
     * Mixed keyed-and-list arrays (valid PHP but not a Livewire shape) are
     * treated as keyed for safety — the list slots would end up on
     * auto-incremented integer keys, which aren't useful as `rules()` keys, so
     * the bail is correct.
     *
     * `as:` / `message:` / `onUpdate:` / `attribute:` / `translate:` named
     * args are only applied to the single-chain branches in this release.
     * Array-form named args paired with a keyed first arg are not yet
     * expanded — that is Phase 3 work tracked against the array-form spec.
     *
     * @return list<array{name: ?string, expr: Expr}>|null
     */
    private function convertAttributeToEntries(Attribute $attr, Property $property, Class_ $class): ?array
    {
        if ($attr->args === []) {
            return null;
        }

        $ruleArg = $attr->args[0] ?? null;

        if (! $ruleArg instanceof Arg) {
            return null;
        }

        if ($ruleArg->value instanceof Array_ && $this->isKeyedArrayAttribute($ruleArg->value)) {
            $entries = $this->convertKeyedAttributeToEntries($ruleArg->value, $attr, $property, $class);

            if ($entries === null) {
                return null;
            }

            return $this->applyKeyedLabels($entries, $this->extractKeyedLabels($attr));
        }

        $fluent = $this->convertSingleValueRuleArg($ruleArg->value, $property, $class);

        if (! $fluent instanceof Expr) {
            return null;
        }

        $label = $this->extractRootLabel($attr);

        if ($label !== null) {
            $fluent = new MethodCall($fluent, new Identifier('label'), [
                new Arg(new String_($label)),
            ]);
        }

        $this->logUnsupportedAttributeArgs($attr, $property, $class);

        return [['name' => null, 'expr' => $fluent]];
    }

    private function convertSingleValueRuleArg(Expr $value, Property $property, Class_ $class): ?Expr
    {
        if ($value instanceof String_) {
            return $this->convertStringToFluentRule($value->value, $this->resolvePropertyTypeHint($property));
        }

        if ($value instanceof Array_) {
            return $this->convertArrayAttributeArg($value, $property, $class);
        }

        return null;
    }

    /**
     * Convert an array-form attribute arg (`#[Rule(['required', 'email'])]`)
     * into a FluentRule chain via the shared `convertArrayToFluentRule()`
     * entry point on `ConvertsValidationRuleArrays`. Emits specific skip-log
     * entries for the two failure modes so users can distinguish "empty
     * rules array" from "array contains elements the converter can't handle."
     */
    private function convertArrayAttributeArg(Array_ $rulesArray, Property $property, Class_ $class): ?Expr
    {
        if ($rulesArray->items === []) {
            $this->logSkip($class, sprintf(
                '#[Rule] attribute on property $%s has an empty rules array — attribute conversion skipped',
                $this->firstPropertyName($property),
            ));

            return null;
        }

        $fluent = $this->convertArrayToFluentRule($rulesArray, inAttributeContext: true);

        if (! $fluent instanceof Expr) {
            $this->logSkip($class, sprintf(
                '#[Rule] attribute on property $%s contains unsupported array elements (closures, variable args, or custom rule objects) — converter bailed',
                $this->firstPropertyName($property),
            ));

            return null;
        }

        return $fluent;
    }

    /**
     * Read the property's PHP type declaration (`public string $x`,
     * `public ?int $y`, etc.) and map the inner scalar to a key the
     * shared TYPE_MAP understands. Returns null for nullable-without-type,
     * union types, intersection types, and class types — none of which
     * map cleanly to a single FluentRule factory.
     */
    private function resolvePropertyTypeHint(Property $property): ?string
    {
        $type = $property->type;

        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        if (! $type instanceof Identifier) {
            return null;
        }

        $name = strtolower($type->toString());

        // Only return scalar names that the trait's TYPE_MAP recognizes.
        // Anything else (object types, custom classes, mixed, etc.) falls
        // through to the no-hint behavior so we don't emit a wrong factory.
        return in_array($name, ['string', 'int', 'integer', 'bool', 'boolean', 'float', 'array'], true)
            ? $name
            : null;
    }

    /**
     * Install the collected property→rule entries into the class as a
     * `rules(): array` method. Merges into an existing simple
     * `return [...]` method; bails with a skip log otherwise.
     *
     * @param  list<array{name: string, expr: Expr}>  $entries
     */
    private function installRulesMethod(Class_ $class, array $entries): bool
    {
        $existing = $this->findRulesMethod($class);

        if ($existing instanceof ClassMethod) {
            return $this->mergeIntoExistingRulesMethod($class, $existing, $entries);
        }

        $visibility = $this->resolveGeneratedRulesVisibility($class);

        if ($visibility === null) {
            $this->logSkip($class, 'parent class declares final rules() method; cannot override — skipping to avoid fatal-on-load');

            return false;
        }

        $items = array_map(
            static fn (array $entry): ArrayItem => new ArrayItem($entry['expr'], new String_($entry['name'])),
            $entries,
        );

        $method = new ClassMethod(
            new Identifier('rules'),
            [
                'flags' => $visibility,
                'returnType' => new Identifier('array'),
                'stmts' => [new Return_($this->multilineArray($items))],
            ],
        );

        // Annotate against Laravel's `ValidationRule` interface union with
        // the other rules() entry shapes. Background on why this is the
        // right supertype:
        //
        // `FluentRule` is intentionally a pure static factory (never
        // instantiated). Its factory methods return concrete rule classes
        // (`EmailRule`, `StringRule`, `FieldRule`, etc.) that all implement
        // `Illuminate\Contracts\Validation\ValidationRule`, but don't share
        // a supertype with `FluentRule` itself. Annotating with `FluentRule`
        // in a type position — as 0.4.3 through 0.4.13 did — is a lie that
        // PHPStan correctly flags (FluentRule and EmailRule are disjoint
        // types).
        //
        // `ValidationRule` covers every shape the rector emits via
        // `convertStringToFluentRule()` / `convertArrayAttributeArg()`.
        // `string` and `array<mixed>` cover the Laravel-native rule forms
        // a user might add via manual edit to the generated method over
        // time (raw pipe-delimited strings, array-tuple rules).
        //
        // Tighter than `array<string, mixed>` (which type-perfect /
        // type-coverage flag as "too broad, narrow your type") while
        // avoiding the disjoint-type lie `FluentRule` introduced.
        $method->setDocComment(new Doc(
            "/**\n * @return array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule|string|array<mixed>>\n */",
        ));

        // Emit a blank line (Nop) before the appended rules() method so it
        // doesn't sit flush against whatever the previous member is. Pint's
        // class_attributes_separation fixer would otherwise fire on every
        // converted file. Skip the Nop when the previous stmt is already a
        // Nop (don't double-space) or when the class body is empty.
        $lastStmt = end($class->stmts);

        if ($lastStmt !== false && ! $lastStmt instanceof Nop) {
            $class->stmts[] = new Nop();
        }

        $class->stmts[] = $method;

        return true;
    }

    /**
     * Build an Array_ flagged for one-item-per-line emission. Rector's
     * BetterStandardPrinter honors NEWLINED_ARRAY_PRINT to force multi-line
     * output regardless of item count; without the attribute the synthesized
     * return array collapses onto a single line and quickly blows past
     * reasonable line widths when consumers have 3+ properties.
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
     * @param  list<array{name: string, expr: Expr}>  $entries
     */
    private function mergeIntoExistingRulesMethod(Class_ $class, ClassMethod $method, array $entries): bool
    {
        $returnArray = $this->findSimpleReturnArray($method);

        if (! $returnArray instanceof Array_) {
            $this->logSkip($class, 'existing rules() method is not a simple `return [...]` — attribute rules left in place');

            return false;
        }

        foreach ($entries as $entry) {
            $returnArray->items[] = new ArrayItem($entry['expr'], new String_($entry['name']));
        }

        // Merging new entries into an existing rules() body invalidates any
        // author-written narrow `@return` annotation that was accurate for
        // the pre-merge shape. See NormalizesRulesDocblock for the rationale
        // and mijntp's 0.4.14 finding on 5 production files.
        $this->normalizeRulesDocblockIfStale($method);

        return true;
    }

    /**
     * Detect whether any method in the class calls `$this->validate([...])`
     * or `$this->validateOnly($field, [...])` with an explicit rules-array
     * argument. That signals the class uses explicit per-call rule arrays
     * rather than the attribute-based rules, so the attribute conversion
     * would produce dead code.
     *
     * Livewire signatures:
     *   validate(array $rules = null, ...)              — rules at arg 0
     *   validateOnly(string $field, array $rules = null, ...) — rules at arg 1
     *
     * `validateOnly($field)` with no rules arg uses the component's `rules()`
     * method (or attributes), so conversion remains safe there — only the
     * explicit-rules form triggers the bail.
     */
    /**
     * Whether any rule key this property's attrs would emit overlaps the
     * explicit-validate skip set populated by `shouldProcessClass`. For
     * simple single-chain attrs the emit key is the property name; for
     * keyed-first-arg attrs (`#[Validate(['todos' => ..., 'todos.*' => ...])]`)
     * the emit keys are the internal string keys. Checking property name
     * alone would miss keyed overlaps (Codex review 2026-04-24).
     *
     * Only fires in partial-overlap mode; in bail/default modes the skip
     * set is empty and this is a cheap no-op.
     */
    private function propertyHasPartialOverlap(Property $property): bool
    {
        if ($this->partialOverlapSkipKeys === []) {
            return false;
        }

        foreach ($this->predictEmitKeysForProperty($property) as $key) {
            if (isset($this->partialOverlapSkipKeys[$key])) {
                return true;
            }
        }

        return false;
    }

    private function findRulesMethod(Class_ $class): ?ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if ($this->isName($method, 'rules')) {
                return $method;
            }
        }

        return null;
    }

    private function findSimpleReturnArray(ClassMethod $method): ?Array_
    {
        if ($method->stmts === null || count($method->stmts) !== 1) {
            return null;
        }

        $stmt = $method->stmts[0];

        if (! $stmt instanceof Return_) {
            return null;
        }

        if (! $stmt->expr instanceof Array_) {
            return null;
        }

        return $stmt->expr;
    }

    private function firstPropertyName(Property $property): string
    {
        if ($property->props === []) {
            return 'unknown';
        }

        return $property->props[0]->name->toString();
    }

    /**
     * Clone an expression so the same FluentRule chain can be attached to
     * each property in a grouped declaration (`public string $a, $b;`).
     */
    private function cloneExpr(Expr $expr): Expr
    {
        return clone $expr;
    }

    private function queueFluentRuleImport(): void
    {
        if (! $this->needsFluentRuleImport) {
            return;
        }

        $this->useNodesToAddCollector->addUseImport(new FullyQualifiedObjectType(FluentRule::class));
        $this->needsFluentRuleImport = false;
    }

    /**
     * 0.20.2 NormalizesRulesDocblock hook implementation. See
     * NormalizesRulesDocblock::queueValidationRuleUseImport().
     */
    protected function queueValidationRuleUseImport(): void
    {
        $this->useNodesToAddCollector->addUseImport(new FullyQualifiedObjectType(self::VALIDATION_RULE_CONTRACT_FQN));
    }

    /**
     * Required by the ConvertsValidationRuleStrings trait contract (inherited
     * via ConvertsValidationRuleArrays). This rector
     * operates at the attribute level, not on array-of-rules maps, so the
     * implementation is a no-op; rule-string conversion lives in the
     * trait's shared convertStringToFluentRule() helper, called directly
     * from convertAttributeToFluentExpr().
     */
    private function processValidationRules(Array_ $array): bool
    {
        return false;
    }
}
