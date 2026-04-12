<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Rector\Concerns\ConvertsValidationRules;
use SanderMuller\FluentValidationRector\Rector\Concerns\ManagesNamespaceImports;
use SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRuleRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Converts string validation rules to FluentRule method chains.
 *
 * Before: 'name' => 'required|string|max:255'
 * After:  'name' => FluentRule::string()->required()->max(255)
 *
 * @see ValidationStringToFluentRuleRectorTest
 */
final class ValidationStringToFluentRuleRector extends AbstractRector implements DocumentedRuleInterface
{
    use ConvertsValidationRules;
    use ManagesNamespaceImports;

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert string validation rules to FluentRule method chains.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'nullable|numeric|min:0|max:120',
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
            'name' => FluentRule::string()->required()->max(255),
            'email' => FluentRule::email()->required(),
            'age' => FluentRule::numeric()->nullable()->min(0)->max(120),
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

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Namespace_) {
            return null;
        }

        $this->needsFluentRuleImport = false;
        $hasChanged = false;

        $this->traverseNodesWithCallable($node, function (Node $inner) use (&$hasChanged): ?Node {
            if ($inner instanceof ClassLike) {
                $result = $this->refactorFormRequest($inner);

                if ($result instanceof Node) {
                    $hasChanged = true;

                    return $result;
                }
            }

            if ($inner instanceof MethodCall && $this->isName($inner->name, 'validate')) {
                $result = $this->refactorValidateCall($inner);

                if ($result instanceof Node) {
                    $hasChanged = true;

                    return $result;
                }
            }

            if (($inner instanceof StaticCall || $inner instanceof MethodCall) && $this->isName($inner->name, 'make')) {
                $result = $this->refactorValidatorMake($inner);

                if ($result instanceof Node) {
                    $hasChanged = true;

                    return $result;
                }
            }

            return null;
        });

        if (! $hasChanged) {
            return null;
        }

        // Any conversion path that flips $hasChanged routes through
        // buildFluentRuleFactory() and emits a short `FluentRule::` reference,
        // so the `use` import is always required here.
        $this->ensureUseImportInNamespace($node, FluentRule::class);

        return $node;
    }

    private function processValidationRules(Array_ $array): bool
    {
        $changed = false;

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if (! $item->key instanceof Expr) {
                continue;
            }

            if (! $item->value instanceof String_) {
                continue;
            }

            $fluent = $this->convertStringToFluentRule($item->value->value);

            if (! $fluent instanceof Expr) {
                continue;
            }

            $item->value = $fluent;
            $changed = true;
        }

        return $changed;
    }

    private function convertStringToFluentRule(string $ruleString): ?Expr
    {
        $parts = explode('|', $ruleString);

        if ($parts === ['']) {
            return null;
        }

        $type = null;
        $modifiers = [];

        foreach ($parts as $part) {
            $parsed = $this->parseRulePart($part);
            $normalized = $this->normalizeRuleName($parsed['name']);

            if ($type === null && isset(self::TYPE_MAP[$normalized])) {
                $type = self::TYPE_MAP[$normalized];

                continue;
            }

            $modifiers[] = ['name' => $normalized, 'args' => $parsed['args']];
        }

        $resolvedType = $type ?? 'field';
        $expr = $this->buildFluentRuleFactory($resolvedType);

        foreach ($modifiers as $modifier) {
            if (! $this->isModifierValidForType($resolvedType, $modifier['name'])) {
                // Use ->rule('name:args') escape hatch for modifiers not available on this type
                $ruleString = $modifier['args'] !== null
                    ? $modifier['name'] . ':' . $modifier['args']
                    : $modifier['name'];
                $expr = new MethodCall($expr, new Identifier('rule'), [
                    new Arg(new String_($ruleString)),
                ]);

                continue;
            }

            $methodCall = $this->buildModifierCall($expr, $modifier['name'], $modifier['args']);

            if (! $methodCall instanceof MethodCall) {
                // Unknown modifier → escape hatch
                $ruleString = $modifier['args'] !== null
                    ? $modifier['name'] . ':' . $modifier['args']
                    : $modifier['name'];
                $expr = new MethodCall($expr, new Identifier('rule'), [
                    new Arg(new String_($ruleString)),
                ]);

                continue;
            }

            $expr = $methodCall;
        }

        return $expr;
    }
}
