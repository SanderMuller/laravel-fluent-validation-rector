<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitor;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\ManagesTraitInsertion;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTraitRectorTest;
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
            $this->logSkip($class, 'already has HasFluentRules trait');

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
            $this->logSkip($class, 'extends a configured base class (trait inherited from parent)');

            return false;
        }

        // Auto-detect inherited trait via the ancestor chain so the rector
        // doesn't re-add HasFluentRules to every subclass of a parent that
        // already declares it. Complements the explicit base_classes config
        // for codebases that don't want to enumerate every shared base.
        if ($this->anyAncestorUsesTrait($class, HasFluentRules::class)) {
            $this->logSkip($class, 'parent class already uses HasFluentRules (trait inherited)');

            return false;
        }

        // Skip abstract classes — subclasses may transform rules via mergeRecursive
        if ($class->isAbstract()) {
            $this->logSkip($class, 'abstract class (subclasses may transform rules via mergeRecursive)');

            return false;
        }

        if (! $this->usesFluentRule($class)) {
            $this->logSkip($class, 'no FluentRule usage in rules() method');

            return false;
        }

        return true;
    }

    private function isLivewireClass(Class_ $class): bool
    {
        // Direct parent name match
        if ($class->extends instanceof Name) {
            $parentName = $this->getName($class->extends);

            if (in_array($parentName, ['Livewire\Component', 'Livewire\Form'], true)) {
                return true;
            }
        }

        // Already has a Livewire trait
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                if ($this->getName($trait) === HasFluentValidation::class) {
                    return true;
                }
            }
        }

        // Heuristic: classes with a render() method are Livewire components/forms,
        // even if they extend intermediate base classes.
        foreach ($class->getMethods() as $method) {
            if ($this->isName($method, 'render')) {
                return true;
            }
        }

        return false;
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
                if ($node instanceof StaticCall
                    && $this->getName($node->class) === FluentRule::class) {
                    $found = true;

                    return NodeVisitor::STOP_TRAVERSAL;
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
