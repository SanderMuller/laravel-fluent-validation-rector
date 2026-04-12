<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\PrettyPrinter\Standard;
use Rector\PostRector\Collector\UseNodesToAddCollector;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Rector\Concerns\ConvertsValidationRules;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttributeRectorTest;
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
    use ConvertsValidationRules;
    use LogsSkipReasons;

    public function __construct(private readonly UseNodesToAddCollector $useNodesToAddCollector) {}

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

        if (! $ruleArg instanceof Arg || ! $ruleArg->value instanceof String_) {
            // Array-form attribute args (`#[Rule(['nullable', 'email'])]`) are not
            // converted in this release — scheduled for a later release once the
            // array-converter's helpers are shareable. Leave the attribute alone
            // so users can migrate manually.
            if ($ruleArg instanceof Arg && $ruleArg->value instanceof Array_) {
                $this->logSkip($class, sprintf(
                    '#[Rule] attribute on property $%s uses array-form rules; manual migration required',
                    $this->firstPropertyName($property),
                ));
            }

            return null;
        }

        $fluent = $this->convertStringToFluentRule($ruleArg->value->value);

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
            $fluent->setAttribute('comments', [new Comment(
                '// TODO: migrate dropped #[Rule] args (' . $unsupportedSummary . ') to messages()/hooks manually',
            )]);

            $this->logSkip($class, sprintf(
                '#[Rule] attribute on property $%s dropped unsupported args: %s',
                $this->firstPropertyName($property),
                $unsupportedSummary,
            ));
        }

        return $fluent;
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

            if (in_array($name, ['messages', 'onUpdate'], true)) {
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
                'stmts' => [new Return_(new Array_($items))],
            ],
        );

        $class->stmts[] = $method;

        return true;
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
     * Required by the ConvertsValidationRules trait contract. This rector
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
