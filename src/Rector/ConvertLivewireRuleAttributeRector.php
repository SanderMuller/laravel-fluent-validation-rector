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
use PhpParser\NodeVisitor;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PostRector\Collector\UseNodesToAddCollector;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Rector\Concerns\ConvertsValidationRuleArrays;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsLivewireRuleAttributes;
use SanderMuller\FluentValidationRector\Rector\Concerns\ExpandsKeyedAttributeArrays;
use SanderMuller\FluentValidationRector\Rector\Concerns\ExtractsLivewireAttributeLabels;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\ReportsLivewireAttributeArgs;
use SanderMuller\FluentValidationRector\Rector\Concerns\ResolvesInheritedRulesVisibility;
use SanderMuller\FluentValidationRector\Rector\Concerns\ResolvesRealtimeValidationMarker;
use SanderMuller\FluentValidationRector\RunSummary;
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

    use ConvertsValidationRuleArrays;
    use DetectsLivewireRuleAttributes;
    use ExpandsKeyedAttributeArrays;
    use ExtractsLivewireAttributeLabels;
    use LogsSkipReasons;
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

        $collected = $this->collectRuleEntries($node);

        if ($collected === []) {
            return null;
        }

        if (! $this->installRulesMethod($node, $collected)) {
            return null;
        }

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
        if (! $this->hasAnyLivewireRuleAttribute($class)) {
            return false;
        }

        if ($this->hasExplicitValidateCall($class)) {
            $this->logSkip($class, 'class calls $this->validate([...]) with explicit args — attribute conversion skipped to avoid generating dead-code rules()');

            return false;
        }

        if ($this->resolveGeneratedRulesVisibility($class) === null) {
            $this->logSkip($class, 'parent class declares final rules() method; cannot override — skipping to avoid fatal-on-load');

            return false;
        }

        return true;
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

        $fluent = $this->convertArrayToFluentRule($rulesArray);

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
    private function hasExplicitValidateCall(Class_ $class): bool
    {
        $found = false;

        $this->traverseNodesWithCallable($class, function (Node $inner) use (&$found): ?int {
            if ($found) {
                return NodeVisitor::STOP_TRAVERSAL;
            }

            if (! $inner instanceof MethodCall) {
                return null;
            }

            if ($this->isName($inner->name, 'validate')) {
                $rulesArg = $inner->args[0] ?? null;
            } elseif ($this->isName($inner->name, 'validateOnly')) {
                $rulesArg = $inner->args[1] ?? null;
            } else {
                return null;
            }

            if (! $rulesArg instanceof Arg) {
                return null;
            }

            // Accept both direct Array_ args (validate(['x' => ...])) and
            // wrapped calls like validate(RuleSet::compileToArrays([...]))
            // that pass an Array_ or Expr to a helper. The common signal is
            // "user is providing rules at call time" — any non-null arg at
            // the rules-position meets that bar for the hybrid-bail heuristic.
            $found = true;

            return NodeVisitor::STOP_TRAVERSAL;
        });

        return $found;
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
