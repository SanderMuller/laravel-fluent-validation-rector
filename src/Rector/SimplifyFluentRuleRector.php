<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\RunSummary;
use SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\SimplifyFluentRuleRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use WeakMap;

/**
 * Simplifies FluentRule method chains by applying factory shortcuts
 * and removing redundant calls.
 *
 * @see SimplifyFluentRuleRectorTest
 */
final class SimplifyFluentRuleRector extends AbstractRector implements DocumentedRuleInterface
{
    private const array FACTORY_SHORTCUTS = [
        'string' => [
            'url' => 'url',
            'uuid' => 'uuid',
            'ulid' => 'ulid',
            'ip' => 'ip',
            // 1.19.0 additions — zero-arg StringRule shortcuts.
            'ipv4' => 'ipv4',
            'ipv6' => 'ipv6',
            'macAddress' => 'macAddress',
            'json' => 'json',
            'timezone' => 'timezone',
            'hexColor' => 'hexColor',
            'activeUrl' => 'activeUrl',
        ],
        'numeric' => ['integer' => 'integer'],
        // 1.19.0 addition — zero-arg ArrayRule shortcut.
        'array' => ['list' => 'list'],
    ];

    /**
     * Factory shortcuts where the chained method carries args that must
     * promote to the new factory's args. `FluentRule::string()->regex($p)`
     * → `FluentRule::regex($p)` and `FluentRule::field()->enum($t)` →
     * `FluentRule::enum($t)`. Distinct from `FACTORY_SHORTCUTS` because
     * the existing transform gates on `$method['args'] === []`.
     *
     * Conservative gate: fires only when the source factory is arg-less
     * AND the chain has no `label()` call. Both conditions prevent silent
     * label loss; positional-slot threading (`regex($pattern, $label)`,
     * `enum($type, $cb, $label)`) is out of v1 scope, so when a label is
     * present we leave the chain alone and let the user collapse manually.
     */
    private const array FACTORY_SHORTCUTS_WITH_ARGS = [
        'string' => ['regex' => 'regex'],
        'field' => ['enum' => 'enum'],
    ];

    private const array REDUNDANT_ON_FACTORY = [
        'url' => ['url'], 'uuid' => ['uuid'], 'ulid' => ['ulid'],
        'ip' => ['ip'], 'integer' => ['integer'],
        // 1.19.0 additions — redundant zero-arg type calls on the new factories.
        'ipv4' => ['ipv4'], 'ipv6' => ['ipv6'], 'macAddress' => ['macAddress'],
        'json' => ['json'], 'timezone' => ['timezone'], 'hexColor' => ['hexColor'],
        'activeUrl' => ['activeUrl'], 'list' => ['list'],
    ];

    private const array LABEL_FIRST_FACTORIES = [
        'string', 'numeric', 'integer', 'date', 'dateTime', 'boolean',
        'file', 'image', 'field', 'url', 'uuid', 'ulid', 'ip',
        // 1.19.0 additions — all accept `?string $label` as last positional arg.
        // `enum` excluded — its label is the THIRD positional, so the
        // single-positional-arg label-promotion path doesn't fit.
        'ipv4', 'ipv6', 'macAddress', 'json', 'timezone', 'hexColor',
        'activeUrl', 'regex', 'list', 'declined',
    ];

    /** @var WeakMap<MethodCall, true> */
    private WeakMap $processedChains;

    public function __construct()
    {
        $this->processedChains = new WeakMap();
        RunSummary::registerShutdownHandler();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Simplify FluentRule chains by using factory shortcuts and removing redundant calls.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
FluentRule::numeric()->integer()->required()->min(0);
FluentRule::string()->url()->nullable();
FluentRule::string()->label('Name')->required();
FluentRule::string()->required()->min(2)->max(255);
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
FluentRule::integer()->required()->min(0);
FluentRule::url()->nullable();
FluentRule::string('Name')->required();
FluentRule::string()->required()->between(2, 255);
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
        // Skip if already processed as part of a larger chain
        if (isset($this->processedChains[$node])) {
            return null;
        }

        // Only process if this is part of a FluentRule chain
        $chain = $this->flattenChain($node);

        if ($chain === null) {
            return null;
        }

        // Mark all intermediate MethodCall nodes as processed
        $current = $node;
        while ($current instanceof MethodCall) {
            if ($current !== $node) {
                $this->processedChains[$current] = true;
            }

            $current = $current->var;
        }

        $simplified = $this->simplifyChain($chain);

        if ($simplified === null) {
            return null;
        }

        return $this->rebuildChain($simplified);
    }

    /**
     * @return array{factory: array{name: string, args: list<Arg>}, methods: list<array{name: string, args: list<Arg>}>}|null
     */
    private function flattenChain(Node $node): ?array
    {
        // Walk inward to find the StaticCall at the root
        $methods = [];
        $current = $node;

        while ($current instanceof MethodCall) {
            if (! $current->name instanceof Identifier) {
                return null;
            }

            /** @var list<Arg> $args */
            $args = [];

            foreach ($current->args as $a) {
                if ($a instanceof Arg) {
                    $args[] = $a;
                }
            }

            array_unshift($methods, ['name' => $current->name->toString(), 'args' => $args]);
            $current = $current->var;
        }

        if (! $current instanceof StaticCall
            || ! $current->name instanceof Identifier
            || ! $this->isObjectType($current->class, new ObjectType(FluentRule::class))) {
            return null;
        }

        /** @var list<Arg> $factoryArgs */
        $factoryArgs = [];

        foreach ($current->args as $a) {
            if ($a instanceof Arg) {
                $factoryArgs[] = $a;
            }
        }

        return [
            'factory' => ['name' => $current->name->toString(), 'args' => $factoryArgs],
            'methods' => $methods,
        ];
    }

    /**
     * @param  array{factory: array{name: string, args: list<Arg>}, methods: list<array{name: string, args: list<Arg>}>}  $chain
     * @return array{factory: array{name: string, args: list<Arg>}, methods: list<array{name: string, args: list<Arg>}>}|null
     */
    private function simplifyChain(array $chain): ?array
    {
        $changed = false;
        $factory = $chain['factory'];
        $methods = $chain['methods'];

        // Pattern 1: Factory shortcuts — string()->url() → url()
        $shortcutResult = $this->tryFactoryShortcuts($factory, $methods);

        if ($shortcutResult !== null) {
            $factory = $shortcutResult['factory'];
            $methods = $shortcutResult['methods'];
            $changed = true;
        }

        // Pattern 1b: Arg-carrying factory shortcuts (1.19.0).
        $argCarryingResult = $this->tryFactoryShortcutsWithArgs($factory, $methods);

        if ($argCarryingResult !== null) {
            $factory = $argCarryingResult['factory'];
            $methods = $argCarryingResult['methods'];
            $changed = true;
        }

        // Pattern 13: Remove redundant type calls
        $redundantResult = $this->tryRemoveRedundantTypeCalls($factory, $methods);

        if ($redundantResult !== null) {
            $methods = $redundantResult;
            $changed = true;
        }

        // Pattern 2: label() → factory arg
        $labelResult = $this->tryPromoteLabelToFactoryArg($factory, $methods);

        if ($labelResult !== null) {
            $factory = $labelResult['factory'];
            $methods = $labelResult['methods'];
            $changed = true;
        }

        // Pattern 11: min(x)->max(y) → between(x, y).
        $betweenResult = $this->tryFoldMinMaxIntoBetween($methods);

        if ($betweenResult !== null) {
            $methods = $betweenResult;
            $changed = true;
        }

        return $changed ? ['factory' => $factory, 'methods' => $methods] : null;
    }

    /**
     * Try Pattern 11: collapse adjacent `min(x)` + `max(y)` calls into
     * a single `between(x, y)`. Bails when either method carries
     * messages (`messageFor('min'/'max')` or positional `message()`
     * adjacent to min/max), since the message keys would rebind from
     * min/max to between.
     *
     * @param  list<array{name: string, args: list<Arg>}>  $methods
     * @return list<array{name: string, args: list<Arg>}>|null
     */
    private function tryFoldMinMaxIntoBetween(array $methods): ?array
    {
        if ($this->hasMinMaxMessages($methods) || $this->hasPositionalMessageNearMinMax($methods)) {
            return null;
        }

        $minIdx = null;
        $maxIdx = null;

        foreach ($methods as $i => $method) {
            if ($method['name'] === 'min' && count($method['args']) === 1) {
                $minIdx = $i;
            }

            if ($method['name'] === 'max' && count($method['args']) === 1) {
                $maxIdx = $i;
            }
        }

        if ($minIdx === null || $maxIdx === null) {
            return null;
        }

        $minArg = $methods[$minIdx]['args'][0];
        $maxArg = $methods[$maxIdx]['args'][0];

        $methods[$minIdx < $maxIdx ? $minIdx : $maxIdx] = [
            'name' => 'between',
            'args' => [$minArg, $maxArg],
        ];
        unset($methods[max($maxIdx, $minIdx)]);

        return array_values($methods);
    }

    /**
     * Pattern 1: zero-arg factory shortcuts (`string()->url()` → `url()`).
     * Existing factory args carry through to the new factory. Returns
     * null when no shortcut applies.
     *
     * @param  array{name: string, args: list<Arg>}  $factory
     * @param  list<array{name: string, args: list<Arg>}>  $methods
     * @return array{factory: array{name: string, args: list<Arg>}, methods: list<array{name: string, args: list<Arg>}>}|null
     */
    private function tryFactoryShortcuts(array $factory, array $methods): ?array
    {
        if (! isset(self::FACTORY_SHORTCUTS[$factory['name']])) {
            return null;
        }

        $shortcuts = self::FACTORY_SHORTCUTS[$factory['name']];

        foreach ($methods as $i => $method) {
            if (isset($shortcuts[$method['name']]) && $method['args'] === []) {
                unset($methods[$i]);

                return [
                    'factory' => ['name' => $shortcuts[$method['name']], 'args' => $factory['args']],
                    'methods' => array_values($methods),
                ];
            }
        }

        return null;
    }

    /**
     * Pattern 13: drop redundant zero-arg type calls (`url()->url()` →
     * `url()`). Returns the modified methods list when at least one
     * call was removed; null when no change.
     *
     * @param  array{name: string, args: list<Arg>}  $factory
     * @param  list<array{name: string, args: list<Arg>}>  $methods
     * @return list<array{name: string, args: list<Arg>}>|null
     */
    private function tryRemoveRedundantTypeCalls(array $factory, array $methods): ?array
    {
        if (! isset(self::REDUNDANT_ON_FACTORY[$factory['name']])) {
            return null;
        }

        $redundant = self::REDUNDANT_ON_FACTORY[$factory['name']];
        $changed = false;

        foreach ($methods as $i => $method) {
            if (in_array($method['name'], $redundant, true) && $method['args'] === []) {
                unset($methods[$i]);
                $changed = true;
            }
        }

        return $changed ? array_values($methods) : null;
    }

    /**
     * Pattern 2: promote `->label('X')` to factory arg
     * (`string()->label('X')` → `string('X')`). Only fires when the
     * factory is arg-less AND in the label-first list. Returns null
     * when no promotion applies.
     *
     * @param  array{name: string, args: list<Arg>}  $factory
     * @param  list<array{name: string, args: list<Arg>}>  $methods
     * @return array{factory: array{name: string, args: list<Arg>}, methods: list<array{name: string, args: list<Arg>}>}|null
     */
    private function tryPromoteLabelToFactoryArg(array $factory, array $methods): ?array
    {
        if ($factory['args'] !== [] || ! in_array($factory['name'], self::LABEL_FIRST_FACTORIES, true)) {
            return null;
        }

        foreach ($methods as $i => $method) {
            if ($method['name'] === 'label' && count($method['args']) === 1) {
                unset($methods[$i]);

                return [
                    'factory' => ['name' => $factory['name'], 'args' => $method['args']],
                    'methods' => array_values($methods),
                ];
            }
        }

        return null;
    }

    /**
     * Try the 1.19.0 arg-carrying factory shortcuts: `string()->regex($p)`
     * → `regex($p)`, `field()->enum($t)` → `enum($t)`. Promotes the
     * chained method's args to the new factory's args. Conservative
     * gate: only fires when the source factory is arg-less AND the
     * chain has no `label()` call (positional-slot threading is out of
     * v1 scope per the 1.19.0 surface spec). Returns null when no
     * promotion applies.
     *
     * @param  array{name: string, args: list<Arg>}  $factory
     * @param  list<array{name: string, args: list<Arg>}>  $methods
     * @return array{factory: array{name: string, args: list<Arg>}, methods: list<array{name: string, args: list<Arg>}>}|null
     */
    private function tryFactoryShortcutsWithArgs(array $factory, array $methods): ?array
    {
        if ($factory['args'] !== []
            || ! isset(self::FACTORY_SHORTCUTS_WITH_ARGS[$factory['name']])
            || $this->chainHasLabelCall($methods)) {
            return null;
        }

        $shortcuts = self::FACTORY_SHORTCUTS_WITH_ARGS[$factory['name']];

        foreach ($methods as $i => $method) {
            if (isset($shortcuts[$method['name']]) && $method['args'] !== []) {
                unset($methods[$i]);

                return [
                    'factory' => ['name' => $shortcuts[$method['name']], 'args' => $method['args']],
                    'methods' => array_values($methods),
                ];
            }
        }

        return null;
    }

    /**
     * Whether the chain contains a `label(...)` call. Used by the
     * arg-carrying factory-shortcut gate to prevent silent label loss
     * when `regex`/`enum` would otherwise consume the chained method's
     * args without threading the label into the new factory's
     * positional slot.
     *
     * @param  list<array{name: string, args: list<Arg>}>  $methods
     */
    private function chainHasLabelCall(array $methods): bool
    {
        foreach ($methods as $method) {
            if ($method['name'] === 'label') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if any messageFor() call targets 'min' or 'max'.
     *
     * @param  list<array{name: string, args: list<Arg>}>  $methods
     */
    private function hasMinMaxMessages(array $methods): bool
    {
        foreach ($methods as $method) {
            if ($method['name'] !== 'messageFor') {
                continue;
            }

            if ($method['args'] === []) {
                continue;
            }

            $firstArg = $method['args'][0];
            $value = $firstArg instanceof Arg ? $firstArg->value : null;

            if ($value instanceof String_
                && in_array($value->value, ['min', 'max', 'between'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a positional message() call appears right after min() or max().
     *
     * @param  list<array{name: string, args: list<Arg>}>  $methods
     */
    private function hasPositionalMessageNearMinMax(array $methods): bool
    {
        for ($i = 0, $count = count($methods); $i < $count; ++$i) {
            if (in_array($methods[$i]['name'], ['min', 'max'], true)
                && isset($methods[$i + 1])
                && $methods[$i + 1]['name'] === 'message') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{factory: array{name: string, args: list<Arg>}, methods: list<array{name: string, args: list<Arg>}>}  $chain
     */
    private function rebuildChain(array $chain): Expr
    {
        $expr = new StaticCall(
            new FullyQualified(FluentRule::class),
            new Identifier($chain['factory']['name']),
            $chain['factory']['args'],
        );

        foreach ($chain['methods'] as $method) {
            $expr = new MethodCall($expr, new Identifier($method['name']), $method['args']);
        }

        return $expr;
    }
}
