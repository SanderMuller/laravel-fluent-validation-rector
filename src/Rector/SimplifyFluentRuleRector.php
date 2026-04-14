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
        'string' => ['url' => 'url', 'uuid' => 'uuid', 'ulid' => 'ulid', 'ip' => 'ip'],
        'numeric' => ['integer' => 'integer'],
    ];

    private const array REDUNDANT_ON_FACTORY = [
        'url' => ['url'], 'uuid' => ['uuid'], 'ulid' => ['ulid'],
        'ip' => ['ip'], 'integer' => ['integer'],
    ];

    private const array LABEL_FIRST_FACTORIES = [
        'string', 'numeric', 'integer', 'date', 'dateTime', 'boolean',
        'file', 'image', 'field', 'url', 'uuid', 'ulid', 'ip',
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
        if (isset(self::FACTORY_SHORTCUTS[$factory['name']])) {
            $shortcuts = self::FACTORY_SHORTCUTS[$factory['name']];

            foreach ($methods as $i => $method) {
                if (isset($shortcuts[$method['name']]) && $method['args'] === []) {
                    $factory = ['name' => $shortcuts[$method['name']], 'args' => $factory['args']];
                    unset($methods[$i]);
                    $methods = array_values($methods);
                    $changed = true;
                    break;
                }
            }
        }

        // Pattern 13: Remove redundant type calls
        if (isset(self::REDUNDANT_ON_FACTORY[$factory['name']])) {
            foreach ($methods as $i => $method) {
                if (in_array($method['name'], self::REDUNDANT_ON_FACTORY[$factory['name']], true) && $method['args'] === []) {
                    unset($methods[$i]);
                    $methods = array_values($methods);
                    $changed = true;
                }
            }
        }

        // Pattern 2: label() → factory arg
        if ($factory['args'] === [] && in_array($factory['name'], self::LABEL_FIRST_FACTORIES, true)) {
            foreach ($methods as $i => $method) {
                if ($method['name'] === 'label' && count($method['args']) === 1) {
                    $factory['args'] = $method['args'];
                    unset($methods[$i]);
                    $methods = array_values($methods);
                    $changed = true;
                    break;
                }
            }
        }

        // Pattern 11: min(x)->max(y) → between(x, y)
        // Skip when messageFor('min'/'max') exists OR positional message() is
        // adjacent to min/max (would rebind from min/max to between key).
        if (! $this->hasMinMaxMessages($methods) && ! $this->hasPositionalMessageNearMinMax($methods)) {
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

            if ($minIdx !== null && $maxIdx !== null) {
                $minArg = $methods[$minIdx]['args'][0];
                $maxArg = $methods[$maxIdx]['args'][0];

                $methods[$minIdx < $maxIdx ? $minIdx : $maxIdx] = [
                    'name' => 'between',
                    'args' => [$minArg, $maxArg],
                ];
                unset($methods[max($maxIdx, $minIdx)]);
                $methods = array_values($methods);
                $changed = true;
            }
        }

        return $changed ? ['factory' => $factory, 'methods' => $methods] : null;
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
