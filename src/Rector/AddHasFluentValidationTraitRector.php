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
use SanderMuller\FluentValidation\HasFluentValidationForFilament;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsFilamentForms;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\ManagesTraitInsertion;
use SanderMuller\FluentValidationRector\RunSummary;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\AddHasFluentValidationTraitRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds the fluent-validation trait to Livewire components that use FluentRule.
 *
 * Picks the right variant based on Filament presence:
 *
 * - Plain Livewire → `HasFluentValidation` (transparent: overrides validate() /
 *   validateOnly() so existing call sites keep working with FluentRule objects).
 * - Livewire + Filament (`InteractsWithForms` v3/v4 or `InteractsWithSchemas`
 *   v5) → `HasFluentValidationForFilament` (additive: exposes `validateFluent()`
 *   without overriding any Filament methods, so no trait collision).
 *
 * Filament detection walks the ancestor chain via ReflectionClass so subclasses
 * of a shared Filament base get the Filament variant automatically.
 *
 * Swap-on-detect: if the wrong variant is already on a class (e.g. plain
 * `HasFluentValidation` on a class that turns out to use Filament), the rector
 * replaces it with the correct variant. Skipping with the wrong variant in
 * place silently ships a runtime collision.
 *
 * @see AddHasFluentValidationTraitRectorTest
 */
final class AddHasFluentValidationTraitRector extends AbstractRector implements DocumentedRuleInterface
{
    use DetectsFilamentForms;
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
            'Add HasFluentValidation (or HasFluentValidationForFilament, for Filament components) to Livewire components that use FluentRule.',
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

        $needsHasFluentImport = false;
        $needsFilamentImport = false;
        $droppedHasFluent = false;
        $droppedFilament = false;

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Class_) {
                continue;
            }

            $action = $this->resolveActionForClass($stmt);

            if ($action === null) {
                continue;
            }

            $this->applyAction($stmt, $action);

            if ($action['target'] === 'HasFluentValidation') {
                $needsHasFluentImport = true;
            } else {
                $needsFilamentImport = true;
            }

            if (($action['wrong_trait'] ?? null) === 'HasFluentValidation') {
                $droppedHasFluent = true;
            } elseif (($action['wrong_trait'] ?? null) === 'HasFluentValidationForFilament') {
                $droppedFilament = true;
            }
        }

        if (! $needsHasFluentImport && ! $needsFilamentImport) {
            return null;
        }

        if ($needsHasFluentImport) {
            $this->ensureUseImportInNamespace($node, HasFluentValidation::class);
        }

        if ($needsFilamentImport) {
            $this->ensureUseImportInNamespace($node, HasFluentValidationForFilament::class);
        }

        // Drop the swapped-out import when no remaining class in this namespace
        // still uses that trait (directly, on any Class_ stmt). Otherwise leave
        // the import — another class may still depend on it.
        if ($droppedHasFluent && ! $this->anyClassInNamespaceUsesTrait($node, 'HasFluentValidation')) {
            $this->removeUseImportFromNamespace($node, HasFluentValidation::class);
        }

        if ($droppedFilament && ! $this->anyClassInNamespaceUsesTrait($node, 'HasFluentValidationForFilament')) {
            $this->removeUseImportFromNamespace($node, HasFluentValidationForFilament::class);
        }

        return $node;
    }

    /**
     * Returns the action to take on this class, or null to skip.
     *
     * - `['mode' => 'insert', 'target' => $short]` — no trait currently, insert target
     * - `['mode' => 'swap', 'target' => $short, 'wrong_trait' => $shortWrong]` —
     *   wrong variant present, replace with target
     *
     * @return array{mode: 'insert'|'swap', target: 'HasFluentValidation'|'HasFluentValidationForFilament', wrong_trait?: string}|null
     */
    private function resolveActionForClass(Class_ $class): ?array
    {
        if (! $this->isLivewireClass($class)) {
            return null;
        }

        if ($class->isAbstract()) {
            $this->logSkip($class, 'abstract class');

            return null;
        }

        if (! $this->usesFluentRule($class)) {
            return null;
        }

        $isFilamentClass = $this->isFilamentClass($class);
        $target = $isFilamentClass ? 'HasFluentValidationForFilament' : 'HasFluentValidation';
        $wrong = $isFilamentClass ? 'HasFluentValidation' : 'HasFluentValidationForFilament';
        $targetFqn = $isFilamentClass ? HasFluentValidationForFilament::class : HasFluentValidation::class;
        $wrongFqn = $isFilamentClass ? HasFluentValidation::class : HasFluentValidationForFilament::class;

        if ($this->directlyUsesTrait($class, $targetFqn)) {
            $this->logSkip($class, sprintf('already has %s trait', $target));

            return null;
        }

        if ($this->anyAncestorUsesTrait($class, $targetFqn)) {
            $this->logSkip($class, sprintf('parent class already uses %s (trait inherited)', $target));

            return null;
        }

        if ($this->conflictsWithExplicitMethodOverride($class, $target)) {
            $this->logSkip($class, sprintf(
                'class declares %s — %s insertion would conflict',
                $target === 'HasFluentValidation'
                    ? 'validate() or validateOnly() directly'
                    : 'validateFluent() directly',
                $target,
            ));

            return null;
        }

        if ($this->directlyUsesTrait($class, $wrongFqn)) {
            return ['mode' => 'swap', 'target' => $target, 'wrong_trait' => $wrong];
        }

        if ($this->anyAncestorUsesTrait($class, $wrongFqn)) {
            $this->logSkip($class, sprintf(
                'parent class uses %s but this class needs %s — manual fix required',
                $wrong,
                $target,
            ));

            return null;
        }

        return ['mode' => 'insert', 'target' => $target];
    }

    /**
     * @param  array{mode: 'insert'|'swap', target: 'HasFluentValidation'|'HasFluentValidationForFilament', wrong_trait?: string}  $action
     */
    private function applyAction(Class_ $class, array $action): void
    {
        if ($action['mode'] === 'swap' && isset($action['wrong_trait'])) {
            $this->removeDirectTraitUse($class, $action['wrong_trait']);
        }

        $this->insertTraitUseInClass($class, $action['target']);
    }

    private function isLivewireClass(Class_ $class): bool
    {
        if ($class->extends instanceof Name) {
            $parentName = $this->getName($class->extends);

            if (in_array($parentName, ['Livewire\Component', 'Livewire\Form'], true)) {
                return true;
            }
        }

        foreach ($class->getMethods() as $method) {
            if ($this->isName($method, 'render')) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the class itself declares a method that would collide with the
     * trait's public surface. For HasFluentValidation, that's validate() /
     * validateOnly(). For HasFluentValidationForFilament, it's validateFluent()
     * (the only method the trait exposes).
     */
    private function conflictsWithExplicitMethodOverride(Class_ $class, string $target): bool
    {
        $blockingNames = $target === 'HasFluentValidation'
            ? ['validate', 'validateOnly']
            : ['validateFluent'];

        foreach ($class->getMethods() as $method) {
            foreach ($blockingNames as $name) {
                if ($this->isName($method, $name)) {
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
}
