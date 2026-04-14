<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitor;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\ManagesTraitInsertion;
use SanderMuller\FluentValidationRector\RunSummary;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\AddHasFluentValidationTraitRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds HasFluentValidation trait to Livewire components that use FluentRule.
 * This enables FluentRule support in Livewire's validate() and validateOnly().
 *
 * @see AddHasFluentValidationTraitRectorTest
 */
final class AddHasFluentValidationTraitRector extends AbstractRector implements DocumentedRuleInterface
{
    use DetectsInheritedTraits;
    use LogsSkipReasons;
    use ManagesTraitInsertion;

    public function __construct()
    {
        RunSummary::registerShutdownHandler();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add HasFluentValidation trait to Livewire components that use FluentRule.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

class EditUser extends Component
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
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

class EditUser extends Component
{
    use HasFluentValidation;

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

        // Insert the `use SanderMuller\FluentValidation\HasFluentValidation;` at
        // the alphabetically-sorted position. See AddHasFluentRulesTraitRector
        // for the rationale.
        $this->ensureUseImportInNamespace($node, HasFluentValidation::class);

        return $node;
    }

    private function shouldAddTraitToClass(Class_ $class): bool
    {
        // Candidacy gate: only log decisions for classes that are plausible
        // Livewire components. Random Action / Console\Kernel / Collection
        // classes that happen to have a rules() method aren't candidates for
        // this trait; skipping them produces no diagnostic value and inflates
        // the skip log with noise users can't act on.
        if (! $this->isLivewireClass($class)) {
            return false;
        }

        if ($class->isAbstract()) {
            $this->logSkip($class, 'abstract class');

            return false;
        }

        if ($this->alreadyHasTrait($class)) {
            $this->logSkip($class, 'already has HasFluentValidation trait');

            return false;
        }

        // Auto-detect inherited trait so subclasses of a shared Livewire base
        // class that already declares HasFluentValidation don't get the trait
        // re-added redundantly. Complements the existing alreadyHasTrait() check
        // (which only catches direct declaration on this class).
        if ($this->anyAncestorUsesTrait($class, HasFluentValidation::class)) {
            $this->logSkip($class, 'parent class already uses HasFluentValidation (trait inherited)');

            return false;
        }

        if ($this->hasValidateMethodConflict($class)) {
            $this->logSkip($class, 'validate() or validateOnly() method conflict (e.g. Filament InteractsWithForms)');

            return false;
        }

        // No FluentRule in rules() means there's nothing to optimize; the
        // trait would be a no-op. Silent skip — if the user expected the
        // converter to populate FluentRule usage and it didn't, the converter
        // rector's own logs explain why, not this one.
        return $this->usesFluentRule($class);
    }

    private function alreadyHasTrait(Class_ $class): bool
    {
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                $name = $this->getName($trait);

                if ($name === HasFluentValidation::class) {
                    return true;
                }
            }
        }

        return false;
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

        // Heuristic: classes with a render() method are Livewire components/forms
        foreach ($class->getMethods() as $method) {
            if ($this->isName($method, 'render')) {
                return true;
            }
        }

        return false;
    }

    /**
     * HasFluentValidation defines validate() and validateOnly().
     * Skip if the class already has these methods or uses traits that define them.
     */
    private function hasValidateMethodConflict(Class_ $class): bool
    {
        foreach ($class->getMethods() as $method) {
            if ($this->isName($method, 'validate') || $this->isName($method, 'validateOnly')) {
                return true;
            }
        }

        // Check for known conflicting traits (e.g., Filament's InteractsWithForms)
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                $name = $this->getName($trait);

                if ($name !== null && str_contains($name, 'InteractsWithForms')) {
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
        $this->insertTraitUseInClass($class, 'HasFluentValidation');
    }
}
