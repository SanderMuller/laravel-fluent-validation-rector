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
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter\Standard;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PostRector\Collector\UseNodesToAddCollector;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Rector\Concerns\ConvertsValidationRuleArrays;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
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
final class ConvertLivewireRuleAttributeRector extends AbstractRector implements DocumentedRuleInterface
{
    use ConvertsValidationRuleArrays;
    use LogsSkipReasons;

    public function __construct(private readonly UseNodesToAddCollector $useNodesToAddCollector)
    {
        RunSummary::registerShutdownHandler();
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

        // Hybrid classes — those that declare #[Rule]/#[Validate] attributes AND
        // call $this->validate([...]) with an explicit array arg — already rely on
        // the explicit validate() args as the source of truth. Converting the
        // attributes to a rules() method in such classes produces dead code
        // (Livewire would still use the explicit array), which creates noisy
        // diffs. Bail instead; let users consolidate manually if they want.
        if ($this->hasExplicitValidateCall($node)) {
            $this->logSkip($node, 'class calls $this->validate([...]) with explicit args — attribute conversion skipped to avoid generating dead-code rules()');

            return null;
        }

        /** @var list<array{name: string, expr: Expr}> $collected */
        $collected = [];

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }

            $result = $this->extractAndStripRuleAttribute($stmt, $node);

            if (! $result instanceof Expr) {
                continue;
            }

            foreach ($stmt->props as $propertyItem) {
                $collected[] = [
                    'name' => $propertyItem->name->toString(),
                    'expr' => $this->cloneExpr($result),
                ];
            }
        }

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
     * Extract a FluentRule Expr from the property's #[Rule] / #[Validate]
     * attributes and remove those attributes. Returns null when no
     * attribute matched or the attribute shape isn't convertible.
     */
    private function extractAndStripRuleAttribute(Property $property, Class_ $class): ?Expr
    {
        $fluentExpr = null;

        foreach ($property->attrGroups as $groupIndex => $attrGroup) {
            $remainingAttrs = [];

            foreach ($attrGroup->attrs as $attr) {
                if (! $this->isLivewireRuleAttribute($attr)) {
                    $remainingAttrs[] = $attr;

                    continue;
                }

                $converted = $this->convertAttributeToFluentExpr($attr, $property, $class);

                if (! $converted instanceof Expr) {
                    $remainingAttrs[] = $attr;

                    continue;
                }

                // First convertible Rule attribute becomes the chain root; additional
                // attributes on the same property are unusual, so the first one wins.
                if (! $fluentExpr instanceof Expr) {
                    $fluentExpr = $converted;
                }
            }

            if ($remainingAttrs === []) {
                unset($property->attrGroups[$groupIndex]);

                continue;
            }

            $property->attrGroups[$groupIndex] = new AttributeGroup($remainingAttrs);
        }

        $property->attrGroups = array_values($property->attrGroups);

        return $fluentExpr;
    }

    private function isLivewireRuleAttribute(Attribute $attr): bool
    {
        $name = $this->getName($attr->name);

        if ($name === null) {
            return false;
        }

        return in_array($name, [
            'Livewire\Attributes\Rule',
            'Livewire\Attributes\Validate',
            'Rule',
            'Validate',
        ], true);
    }

    private function convertAttributeToFluentExpr(Attribute $attr, Property $property, Class_ $class): ?Expr
    {
        if ($attr->args === []) {
            return null;
        }

        $ruleArg = $attr->args[0] ?? null;

        if (! $ruleArg instanceof Arg) {
            return null;
        }

        if ($ruleArg->value instanceof String_) {
            $fluent = $this->convertStringToFluentRule($ruleArg->value->value, $this->resolvePropertyTypeHint($property));
        } elseif ($ruleArg->value instanceof Array_) {
            $fluent = $this->convertArrayAttributeArg($ruleArg->value, $property, $class);
        } else {
            return null;
        }

        if (! $fluent instanceof Expr) {
            return null;
        }

        $label = $this->extractLabelArg($attr);

        if ($label !== null) {
            $fluent = new MethodCall($fluent, new Identifier('label'), [
                new Arg(new String_($label)),
            ]);
        }

        $unsupportedSummary = $this->describeUnsupportedAttributeArgs($attr);

        if ($unsupportedSummary !== null) {
            // The unsupported payload is logged via logSkip() so users running with
            // FLUENT_VALIDATION_RECTOR_VERBOSE=1 see exactly what got dropped. An
            // in-source `// TODO:` comment would be friendlier but PhpParser's
            // pretty-printer doesn't reliably emit comments attached to sub-expressions
            // inside array items; a proper implementation needs a post-rector pass to
            // inject a Nop+Comment node before the return, which is scoped out of 0.4.0.
            $this->logSkip($class, sprintf(
                '#[Rule] attribute on property $%s dropped unsupported args (%s); migrate to messages() / hooks manually',
                $this->firstPropertyName($property),
                $unsupportedSummary,
            ));
        }

        return $fluent;
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

    private function extractLabelArg(Attribute $attr): ?string
    {
        foreach ($attr->args as $arg) {
            if ($arg->name instanceof Identifier && $arg->name->toString() === 'as' && $arg->value instanceof String_) {
                return $arg->value->value;
            }
        }

        return null;
    }

    /**
     * Describe any attribute args we don't know how to migrate (`messages:`,
     * `onUpdate:`). Used to surface a TODO comment beside the converted
     * chain — peer-requested so manual-migration targets are obvious.
     */
    private function describeUnsupportedAttributeArgs(Attribute $attr): ?string
    {
        $dropped = [];

        foreach ($attr->args as $arg) {
            if (! $arg->name instanceof Identifier) {
                continue;
            }

            $name = $arg->name->toString();

            // Livewire's attribute arg list: `as` (→ ->label()), `message` (singular,
            // per-property message override), `messages` (plural, for attribute chains
            // on a single property — rare but documented), `onUpdate` (lifecycle hook).
            // Only `as` has a direct FluentRule equivalent; the rest get a TODO
            // comment listing the dropped payload so manual migration is obvious.
            if (in_array($name, ['message', 'messages', 'onUpdate'], true)) {
                $dropped[] = $name . ': ' . $this->print($arg->value);
            }
        }

        return $dropped === [] ? null : implode(', ', $dropped);
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

        $items = array_map(
            static fn (array $entry): ArrayItem => new ArrayItem($entry['expr'], new String_($entry['name'])),
            $entries,
        );

        $method = new ClassMethod(
            new Identifier('rules'),
            [
                'flags' => Class_::MODIFIER_PROTECTED,
                'returnType' => new Identifier('array'),
                'stmts' => [new Return_($this->multilineArray($items))],
            ],
        );

        // Pre-empt rector-preset's DocblockReturnArrayFromDirectArrayInstanceRector,
        // which would otherwise add a loose `@return array<string, mixed>`. The
        // generated rules() values are FluentRule chains, raw rule strings (merged
        // into an existing rules() array), or nested arrays for Livewire dotted
        // rules — this annotation is the tightest accurate union without reaching
        // into each entry's concrete type.
        //
        // The short name `FluentRule` is already guaranteed in-scope by the
        // queueFluentRuleImport() call in refactor(); emitting the short alias
        // here keeps the pre-Pint output clean (avoids Pint's
        // fully_qualified_strict_types fixer firing on every converted file).
        $method->setDocComment(new Doc(
            "/**\n * @return array<string, FluentRule|string|array<string, mixed>>\n */",
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

    private function print(Node $node): string
    {
        $printer = new Standard();

        return $printer->prettyPrint([$node]);
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
