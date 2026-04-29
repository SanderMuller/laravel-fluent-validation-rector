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
use SanderMuller\FluentValidationRector\Rector\Concerns\ConvertsValidationRuleArrays;
use SanderMuller\FluentValidationRector\Rector\Concerns\DescendsIntoRuleSetFromWrapper;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsRulesShapedMethods;
use SanderMuller\FluentValidationRector\Rector\Concerns\IdentifiesLivewireClasses;
use SanderMuller\FluentValidationRector\Rector\Concerns\QualifiesForRulesProcessing;
use SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\ValidationArrayToFluentRuleRectorTest;
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
        return [ClassLike::class, MethodCall::class, StaticCall::class, FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof ClassLike) {
            // Class-qualification gate (silent bail). See
            // QualifiesForRulesProcessing for the rationale: prevents
            // every Domain entity / Service / Action from being walked
            // when auto-detection of rules-shaped methods later widens
            // the in-class method discovery beyond literal `rules()`.
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

        // `Validator::validate(<data>, <rules>)` — same arg layout as
        // `Validator::make()`; reuse the same handler. See sibling rector
        // for the rationale.
        if ($node instanceof StaticCall && $this->isName($node->name, 'validate')) {
            return $this->emitWithImport($this->refactorValidatorMake($node));
        }

        // Global `validator(<data>, <rules>)` helper — see sibling rector
        // for rationale on the conservative resolution check.
        if ($node instanceof FuncCall) {
            return $this->emitWithImport($this->refactorGlobalValidatorHelper($node));
        }

        // 1.1.0 broad-scope RuleSet::from descent (OQ #1 = (b)). Mirrors
        // the sibling string-rector path verbatim — visits any
        // `RuleSet::from([Array_])` static call wherever it appears in
        // PHP source. Static-call signature self-gates against false
        // positives; no surrounding-class qualification needed. Both
        // converter rectors share this descent shape because both apply
        // their existing `processValidationRules(Array_)` logic to the
        // wrapper's inner Array_; only the inner conversion rules differ.
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
     * Apply array-form rule conversion to the inner Array_ argument
     * of a `RuleSet::from([Array_])` static call. Inner-only descent
     * per OQ #3 = (a) — the wrapper static call + any chained methods
     * (`->check($data)->safe()`) stay verbatim around the converted
     * inner array.
     *
     * Non-literal-arg case (`RuleSet::from($injected)`) emits a
     * skip-log entry mirroring the wording shared with
     * `GroupWildcardRulesToEachRector` and
     * `ValidationStringToFluentRuleRector` — one consistent skip-reason
     * text across all three rectors for the same failure-mode shape.
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
     * Resolution: bail unless the call unambiguously targets the global
     * `\validator`. PhpParser's `namespacedName` attribute on the Name
     * node signals a possible userland shadow when set to a non-
     * `validator` value.
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
