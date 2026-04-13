<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\ClassLike;
use Rector\PostRector\Collector\UseNodesToAddCollector;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Rector\Concerns\ConvertsValidationRuleArrays;
use SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRuleRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Converts array-based validation rules to FluentRule method chains.
 *
 * Before: 'name' => ['required', 'string', 'max:255']
 * After:  'name' => FluentRule::string()->required()->max(255)
 *
 * Also converts Rule:: objects, Password chains, custom rule objects, and closures within arrays.
 *
 * @see ValidationArrayToFluentRuleRectorTest
 */
final class ValidationArrayToFluentRuleRector extends AbstractRector implements DocumentedRuleInterface
{
    use ConvertsValidationRuleArrays;

    public function __construct(private readonly UseNodesToAddCollector $useNodesToAddCollector) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert array-based validation rules (with optional Rule:: objects) to FluentRule method chains.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(['admin', 'editor'])],
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
            'email' => FluentRule::email()->required()->unique('users', 'email'),
            'role' => FluentRule::field()->required()->in(['admin', 'editor']),
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
        return [ClassLike::class, MethodCall::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof ClassLike) {
            $result = $this->refactorFormRequest($node);

            if ($result instanceof Node) {
                $this->queueFluentRuleImport();
            }

            return $result;
        }

        if ($node instanceof MethodCall && $this->isName($node->name, 'validate')) {
            $result = $this->refactorValidateCall($node);

            if ($result instanceof Node) {
                $this->queueFluentRuleImport();
            }

            return $result;
        }

        if (($node instanceof StaticCall || $node instanceof MethodCall) && $this->isName($node->name, 'make')) {
            $result = $this->refactorValidatorMake($node);

            if ($result instanceof Node) {
                $this->queueFluentRuleImport();
            }

            return $result;
        }

        return null;
    }

    private function queueFluentRuleImport(): void
    {
        if (! $this->needsFluentRuleImport) {
            return;
        }

        $this->useNodesToAddCollector->addUseImport(new FullyQualifiedObjectType(FluentRule::class));
        $this->needsFluentRuleImport = false;
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

            if (! $item->value instanceof Array_) {
                continue;
            }

            $fluent = $this->convertArrayToFluentRule($item->value);

            if (! $fluent instanceof Expr) {
                continue;
            }

            $item->value = $fluent;
            $changed = true;
        }

        return $changed;
    }
}
