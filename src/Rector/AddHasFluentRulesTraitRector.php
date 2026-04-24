<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitor;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\IdentifiesLivewireClasses;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\ManagesTraitInsertion;
use SanderMuller\FluentValidationRector\RunSummary;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\AddHasFluentRulesTraitRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds HasFluentRules trait to FormRequest classes that use FluentRule.
 * This enables optimized validation (fast-checks, batch DB queries, pre-exclusion).
 *
 * @see AddHasFluentRulesTraitRectorTest
 */
final class AddHasFluentRulesTraitRector extends AbstractRector implements ConfigurableRectorInterface, DocumentedRuleInterface
{
    use DetectsInheritedTraits;
    use IdentifiesLivewireClasses;
    use LogsSkipReasons;
    use ManagesTraitInsertion;

    public const string BASE_CLASSES = 'base_classes';

    /**
     * Opt-in list of FormRequest base classes that should receive the trait,
     * even if their own rules() method doesn't use FluentRule. Useful when a
     * project has intermediate base classes (e.g. AjaxFormRequest) whose
     * subclasses use FluentRule — the trait added to the base propagates
     * automatically to all subclasses.
     *
     * @var list<string>
     */
    private array $baseClasses = [];

    public function __construct()
    {
        RunSummary::registerShutdownHandler();
    }

    /** @param  array<string, list<string>>|list<string>  $configuration */
    public function configure(array $configuration): void
    {
        /** @var list<string>|null $baseClasses */
        $baseClasses = $configuration[self::BASE_CLASSES] ?? $configuration;

        if (is_array($baseClasses)) {
            $this->baseClasses = array_values($baseClasses);
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add HasFluentRules trait to FormRequest classes that use FluentRule.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StorePostRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
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

        $hasChanged = false;

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Class_) {
                continue;
            }

            if ($this->shouldAddTraitToClass($stmt)) {
                $this->addTraitToClass($stmt);
                $hasChanged = true;
            }
        }

        if (! $hasChanged) {
            return null;
        }

        // Insert the `use SanderMuller\FluentValidation\HasFluentRules;` import
        // into the namespace at the alphabetically-sorted position. We do this
        // here (rather than via UseNodesToAddCollector) because Rector's default
        // UseAddingPostRector prepends new imports to the top of the use block
        // regardless of alphabetical order.
        $this->ensureUseImportInNamespace($node, HasFluentRules::class);

        return $node;
    }

    private function shouldAddTraitToClass(Class_ $class): bool
    {
        // Skip Livewire classes — they use HasFluentValidation, not HasFluentRules
        if ($this->isLivewireClass($class)) {
            $this->logSkip($class, 'detected as Livewire (uses HasFluentValidation instead)');

            return false;
        }

        if ($this->alreadyHasTrait($class)) {
            $this->logSkip($class, 'already has HasFluentRules trait', verboseOnly: true);

            return false;
        }

        $className = $class->namespacedName instanceof Name ? $class->namespacedName->toString() : null;
        $isConfiguredBase = $className !== null && in_array($className, $this->baseClasses, true);

        // Configured base classes always get the trait (even if abstract or no FluentRule usage)
        if ($isConfiguredBase) {
            return true;
        }

        // Children of configured base classes inherit the trait — skip to avoid duplication
        if ($class->extends instanceof Name && in_array($this->getName($class->extends), $this->baseClasses, true)) {
            $this->logSkip($class, 'extends a configured base class (trait inherited from parent)', verboseOnly: true);

            return false;
        }

        // Auto-detect inherited trait via the ancestor chain so the rector
        // doesn't re-add HasFluentRules to every subclass of a parent that
        // already declares it. Complements the explicit base_classes config
        // for codebases that don't want to enumerate every shared base.
        if ($this->anyAncestorUsesTrait($class, HasFluentRules::class)) {
            $this->logSkip($class, 'parent class already uses HasFluentRules (trait inherited)', verboseOnly: true);

            return false;
        }

        // Skip abstract classes that declare rules() — subclasses may transform
        // that method via mergeRecursive, and the trait on the base would not
        // be correct for every subclass. Gate on hasMethod('rules') so we don't
        // log skips for every unrelated abstract in the codebase (Events,
        // Exceptions, DataObjects, Commands with no validation surface).
        if ($class->isAbstract() && $class->getMethod('rules') instanceof ClassMethod) {
            $this->logSkip($class, 'abstract class with rules() (subclasses may transform via mergeRecursive — add to base_classes config to opt in)');

            return false;
        }

        // No FluentRule in rules() means there's nothing to optimize; the
        // trait would be a no-op. Silent skip — on codebases where `rules()`
        // is a common naming convention for non-validation helpers (Actions,
        // Console\Kernel, Collections), logging this bail inflates the skip
        // log with entries users can't act on.
        return $this->usesFluentRule($class);
    }

    private function alreadyHasTrait(Class_ $class): bool
    {
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                $name = $this->getName($trait);

                if ($name === HasFluentRules::class) {
                    return true;
                }
            }
        }

        return false;
    }

    private function usesFluentRule(Class_ $class): bool
    {
        $found = false;

        foreach ($class->getMethods() as $method) {
            if (! $this->isName($method, 'rules')) {
                continue;
            }

            $this->traverseNodesWithCallable($method, function (Node $node) use (&$found): ?int {
                if ($node instanceof StaticCall) {
                    $className = $this->getName($node->class);

                    // Accept both the fully-qualified name and the short `FluentRule`
                    // form emitted by sibling converter rectors before their queued
                    // `use` import is materialized by the post-rector pipeline.
                    if ($className === FluentRule::class || $className === 'FluentRule') {
                        $found = true;

                        return NodeVisitor::STOP_TRAVERSAL;
                    }
                }

                return null;
            });
        }

        return $found;
    }

    private function addTraitToClass(Class_ $class): void
    {
        $this->insertTraitUseInClass($class, 'HasFluentRules');
    }
}
