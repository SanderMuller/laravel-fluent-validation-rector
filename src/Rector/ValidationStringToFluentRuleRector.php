<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use Rector\PostRector\Collector\UseNodesToAddCollector;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Rector\Concerns\ConvertsValidationRules;
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

    public function __construct(private readonly UseNodesToAddCollector $useNodesToAddCollector) {}

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
}
