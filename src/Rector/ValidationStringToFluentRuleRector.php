<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PostRector\Collector\UseNodesToAddCollector;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Internal\RunSummary;
use SanderMuller\FluentValidationRector\Rector\Concerns\ConvertsValidationRuleStrings;
use SanderMuller\FluentValidationRector\Rector\Concerns\DescendsIntoRuleSetFromWrapper;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsRulesShapedMethods;
use SanderMuller\FluentValidationRector\Rector\Concerns\IdentifiesLivewireClasses;
use SanderMuller\FluentValidationRector\Rector\Concerns\QualifiesForRulesProcessing;
use SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\ValidationStringToFluentRuleRectorTest;
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
    use ConvertsValidationRuleStrings;
    use DescendsIntoRuleSetFromWrapper;
    use DetectsInheritedTraits;
    use DetectsRulesShapedMethods;
    use IdentifiesLivewireClasses;
    use QualifiesForRulesProcessing;

    public function __construct(private readonly UseNodesToAddCollector $useNodesToAddCollector)
    {
        RunSummary::registerShutdownHandler();
    }

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
        return [ClassLike::class, MethodCall::class, StaticCall::class, FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof ClassLike) {
            // Class-qualification gate (silent bail). Without this, the
            // method-name filter inside refactorFormRequest is the only
            // thing preventing every Domain entity / Service / Action
            // from being walked. The gate ensures auto-detection of
            // rules-shaped methods (when shipped) only runs on classes
            // that are FormRequest descendants or use the package's
            // traits — preserving the existing safety boundary.
            if (! $node instanceof Class_ || ! $this->qualifiesForRulesProcessing($node)) {
                return null;
            }

            return $this->emitWithImport($this->refactorFormRequest($node));
        }

        if ($node instanceof MethodCall && $this->isName($node->name, 'validate')) {
            return $this->emitWithImport($this->refactorValidateCall($node));
        }

        if (($node instanceof StaticCall || $node instanceof MethodCall) && $this->isName($node->name, 'make')) {
            return $this->emitWithImport($this->refactorValidatorMake($node));
        }

        // `Validator::validate(<data>, <rules>)` — the one-shot static
        // call. Same arg layout as `Validator::make()` (data, rules) so
        // we reuse `refactorValidatorMake`. The class-resolution check
        // inside `refactorValidatorMake` already gates on
        // `Illuminate\Support\Facades\Validator` (handles aliased
        // imports too).
        if ($node instanceof StaticCall && $this->isName($node->name, 'validate')) {
            return $this->emitWithImport($this->refactorValidatorMake($node));
        }

        // Global `validator(<data>, <rules>)` helper. Conservative
        // resolution — only rewrite when we can prove the call resolves
        // to the global `\validator` function. PhpParser's name resolver
        // attaches a `namespacedName` attribute to FuncCall name nodes
        // when the call sits inside a non-empty namespace WITHOUT a
        // leading backslash — e.g. `validator(...)` inside
        // `namespace App\Foo` gets `namespacedName = App\Foo\validator`.
        // PHP would resolve `App\Foo\validator` first if it existed, so
        // we can't statically prove no userland shadow. Bail in that
        // case. Users wanting the rewrite can prefix with `\` (no
        // namespacedName attached) or write the call in the global
        // namespace.
        if ($node instanceof FuncCall) {
            return $this->emitWithImport($this->refactorGlobalValidatorHelper($node));
        }

        // 1.1.0 broad-scope RuleSet::from descent (OQ #1 = (b)). Visits any
        // `RuleSet::from([Array_])` static call wherever it appears in PHP
        // source — action methods, services, controllers, jobs, plus the
        // FormRequest `rules(): RuleSet` return path. The static-call
        // signature self-gates against false positives; no surrounding-class
        // qualification is needed.
        //
        // Placed after the Validator::* paths so a call like
        // `Validator::validate(...)` is matched first by the validate
        // handler. Both paths are mutually exclusive by name predicate
        // (`from` vs `validate` / `make`); ordering is defensive.
        if ($node instanceof StaticCall && $this->isRuleSetFromCall($node)) {
            return $this->emitWithImport($this->refactorRuleSetFromWrapper($node));
        }

        return null;
    }

    /**
     * Per-branch terminal: queue the FluentRule use-import iff the
     * branch's refactor helper produced a (mutated) node, and return
     * the same node back to Rector. Centralizes the "if rewrite
     * happened, ensure FluentRule is imported" pattern across all
     * refactor() branches so the dispatch method stays under
     * cognitive-complexity budget.
     */
    private function emitWithImport(?Node $result): ?Node
    {
        if ($result instanceof Node) {
            $this->queueFluentRuleImport();
        }

        return $result;
    }

    /**
     * Apply string-rule conversion to the inner Array_ argument of a
     * `RuleSet::from([Array_])` static call. Inner-only descent per
     * OQ #3 = (a) — the wrapper static call + any chained methods
     * (`->check($data)->safe()`) stay verbatim around the converted
     * inner array.
     *
     * Non-literal-arg case (`RuleSet::from($injected)`) emits a skip-log
     * entry mirroring `GroupWildcardRulesToEachRector`'s wording so all
     * three rectors share one consistent skip-reason text for the same
     * failure-mode shape.
     */
    private function refactorRuleSetFromWrapper(StaticCall $node): ?StaticCall
    {
        $innerArray = $this->extractArrayFromRuleSetFrom($node);

        if (! $innerArray instanceof Array_) {
            $scope = $node->getAttribute(AttributeKey::SCOPE);
            $className = 'top-level';

            if ($scope instanceof Scope && $scope->getClassReflection() instanceof ClassReflection) {
                $className = $scope->getClassReflection()->getName();
            }

            $this->logSkipByName(
                $className,
                'RuleSet::from() argument is not a literal array — fold target must be a statically-determinable Array_; consumer audit required',
                location: $node,
            );

            return null;
        }

        return $this->processValidationRules($innerArray) ? $node : null;
    }

    /**
     * Handle the global `validator(<data>, <rules>)` helper. Same arg
     * layout as `Validator::make()` (arg 0 = data, arg 1 = rules).
     *
     * Resolution check: bail unless the call unambiguously targets the
     * global `\validator` function. PhpParser's NameResolver attaches a
     * `namespacedName` attribute on the call's Name node only when
     * resolution would prefer a namespaced version over the global —
     * so its presence (with a non-`validator` value) signals a possible
     * userland shadow we can't statically rule out.
     */
    private function refactorGlobalValidatorHelper(FuncCall $node): ?FuncCall
    {
        if (! $node->name instanceof Name || ! $this->isName($node->name, 'validator')) {
            return null;
        }

        $namespacedName = $node->name->getAttribute('namespacedName');

        if ($namespacedName instanceof Name && $namespacedName->toString() !== 'validator') {
            return null;
        }

        if (count($node->args) < 2 || ! $node->args[1] instanceof Arg) {
            return null;
        }

        $rulesArgument = $node->args[1]->value;

        if (! $rulesArgument instanceof Array_) {
            return null;
        }

        return $this->processValidationRules($rulesArgument) ? $node : null;
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
