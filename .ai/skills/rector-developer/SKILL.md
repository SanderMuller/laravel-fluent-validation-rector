# Rector PHP Rule Builder

Rector transforms PHP code by traversing the PHP-Parser AST, matching node types, and returning modified nodes from `refactor()`.

## Workflow

1. **Check for an existing configurable rule first** — see references/configurable-rules.md. Renaming functions/methods/classes, converting call types, and removing arguments are all covered. Prefer `->withConfiguredRule()` over writing a custom rule for these cases.
2. Identify the PHP-Parser node type(s) to target (see references/node-types.md)
3. Write the rule class extending `AbstractRector`
4. If PHP version gated, implement `MinPhpVersionInterface`
5. If configurable, implement `ConfigurableRectorInterface`
6. Register the rule in rector.php config

## Rule Skeleton

```php
<?php

declare(strict_types=1);

namespace Rector\[Category]\Rector\[NodeType];

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall; // target node type
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Rector\Tests\[Category]\Rector\[NodeType]\[RuleName]\[RuleName]Test
 */
final class [RuleName]Rector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('[Description]', [
            new CodeSample(
                <<<'CODE_SAMPLE'
// before
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
// after
CODE_SAMPLE
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    /** @param FuncCall $node */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isName($node, 'target_function')) {
            return null;
        }

        // transform and return modified $node, or return null for no change
        return $node;
    }
}
```

## `refactor()` Return Values

| Return | Effect |
|--------|--------|
| `null` | No change, continue traversal |
| `$node` (modified) | Replace with modified node |
| `Node[]` (non-empty) | Replace with multiple nodes |
| `NodeVisitor::REMOVE_NODE` | Delete the node |

**Never return an empty array** — throws `ShouldNotHappenException`.

## Protected Methods on AbstractRector

```php
// Name checking
$this->isName($node, 'functionName')         // exact name match
$this->isNames($node, ['name1', 'name2'])    // match any
$this->getName($node)                         // get name string or null

// Type checking (PHPStan-powered)
$this->getType($node)                         // returns PHPStan Type
$this->isObjectType($node, new ObjectType('ClassName'))

// Traversal
$this->traverseNodesWithCallable($nodes, function (Node $node): int|Node|null {
    return null; // continue
    // or return NodeVisitor::STOP_TRAVERSAL;
    // or return NodeVisitor::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
});

// Misc
$this->mirrorComments($newNode, $oldNode);    // copy comments
```

## Creating Class Name Nodes

Always use `Node\Name\FullyQualified` for class references in AST nodes — never `Node\Name`. The string must not have a leading backslash.

## Configurable Rules

```php
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;

final class MyRector extends AbstractRector implements ConfigurableRectorInterface
{
    private string $targetClass = 'OldClass';

    public function configure(array $configuration): void
    {
        $this->targetClass = $configuration['target_class'] ?? $this->targetClass;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('...', [
            new ConfiguredCodeSample('before', 'after', ['target_class' => 'OldClass']),
        ]);
    }
}
```

## PHP Version Gating

```php
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Rector\ValueObject\PhpVersionFeature;

final class MyRector extends AbstractRector implements MinPhpVersionInterface
{
    public function provideMinPhpVersion(): int
    {
        return PhpVersionFeature::ENUM; // PHP 8.1+
    }
}
```

## rector.php Registration

```php
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([MyRector::class])
    // configurable rule:
    ->withConfiguredRule(MyConfigurableRector::class, ['key' => 'value']);
```

## Writing Tests

Every rule needs a test class extending `AbstractRectorTestCase` with fixtures in a `Fixture/` directory and a config in `config/configured_rule.php`.

**Fixture tip:** Write only the input section, run the test, and `FixtureFileUpdater` fills the expected output automatically.

**Skip fixtures:** One `skip_*.php.inc` file per no-change scenario — single section, no `-----` separator.

## Reducing Rule Risk

Before transforming a class or its members, consider whether the change is safe in an inheritance context. Rules that add, remove, or change methods/properties on non-final classes could break subclasses. Guard with `isFinal()` when appropriate.

## Reference Files

- **references/configurable-rules.md** — All built-in configurable rules with config examples (check this before writing a custom rule)
