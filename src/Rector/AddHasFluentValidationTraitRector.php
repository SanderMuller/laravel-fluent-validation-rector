<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\TraitUseAdaptation\Precedence;
use PhpParser\NodeVisitor;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidation\HasFluentValidationForFilament;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsFilamentForms;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\IdentifiesLivewireClasses;
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
 * Picks the right variant based on Filament presence on the class itself:
 *
 * - Plain Livewire (no direct Filament trait) → `HasFluentValidation`.
 * - Livewire + Filament (`InteractsWithForms` v3/v4 or `InteractsWithSchemas`
 *   v5) directly on the class → `HasFluentValidationForFilament` paired with a
 *   4-method `insteadof` adaptation block (validate, validateOnly, getRules,
 *   getValidationAttributes) that resolves the composition collision with the
 *   Filament trait. Both traits override the same four method names, so
 *   without the adaptation PHP fatals at class load.
 *
 * **Ancestor Filament:** when a Filament trait lives on a parent class only
 * (not directly on this class), the rector skip-logs and leaves the class
 * alone. PHP's method resolution for inherited trait compositions is too
 * fragile to assume the subclass's `HasFluentValidationForFilament::validate`
 * correctly forwards to Filament's form-schema validation through `parent::`.
 * The user must add the trait + adaptation block directly on the subclass,
 * or refactor the composition into a shared trait on the base.
 *
 * **Swap-on-detect:** if the wrong variant is already directly on the class
 * (plain `HasFluentValidation` on a class that turns out to be a Filament
 * component, or vice versa), the rector removes it, inserts the correct
 * variant with the right adaptation block, and drops any now-orphaned
 * top-level `use` import.
 *
 * @see AddHasFluentValidationTraitRectorTest
 */
final class AddHasFluentValidationTraitRector extends AbstractRector implements DocumentedRuleInterface
{
    use DetectsFilamentForms;
    use DetectsInheritedTraits;
    use IdentifiesLivewireClasses;
    use LogsSkipReasons;
    use ManagesTraitInsertion;

    /**
     * Methods that `HasFluentValidationForFilament` overrides in main-package
     * `1.8.1+`. All four also exist on Filament's `InteractsWithForms` /
     * `InteractsWithSchemas`, so each needs a `insteadof` entry to resolve
     * the composition collision. `getMessages` is intentionally absent — the
     * FluentValidation trait defines it but Filament's does not.
     *
     * @var list<string>
     */
    private const array FILAMENT_INSTEADOF_METHODS = [
        'validate',
        'validateOnly',
        'getRules',
        'getValidationAttributes',
    ];

    public function __construct()
    {
        RunSummary::registerShutdownHandler();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add HasFluentValidation (or HasFluentValidationForFilament + insteadof adaptation, for Filament components) to Livewire components that use FluentRule.',
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
     * Action struct:
     * - `target`: which trait short-name to insert (`HasFluentValidation` or `HasFluentValidationForFilament`).
     * - `filament_trait`: the Name node for the directly-used Filament trait when present — used to
     *    build the `insteadof` adaptation block. Null for plain-Livewire target.
     * - `wrong_trait`: short-name of the wrong variant to remove before insertion. Null on fresh inserts.
     *
     * @return array{target: 'HasFluentValidation'|'HasFluentValidationForFilament', filament_trait: Name|null, wrong_trait: string|null}|null
     */
    private function resolveActionForClass(Class_ $class): ?array
    {
        if (! $this->isLivewireClass($class)) {
            return null;
        }

        if ($class->isAbstract()) {
            $this->logAbstractLivewireSkip($class);

            return null;
        }

        if (! $this->usesFluentRule($class)) {
            // A Livewire component with a rules() method but no FluentRule
            // usage is actionable: the user either forgot to run the string
            // converter (ValidationStringToFluentRuleRector) or has rules the
            // converter couldn't rewrite. Promote to a skip-log entry so it
            // surfaces in the end-of-run summary instead of silently dropping.
            // Plain Livewire components with no rules() at all get the usual
            // silent skip — they're just not validation-bearing.
            if ($this->hasRulesMethod($class)) {
                $this->logSkip(
                    $class,
                    'Livewire component has rules() but no FluentRule usage — convert string rules to FluentRule (run the ValidationStringToFluentRuleRector set) before this rule fires',
                );
            }

            return null;
        }

        $filamentTrait = $this->findDirectFilamentTrait($class);
        $ancestorHasFilament = ! $filamentTrait instanceof Name && $this->ancestorHasFilamentTrait($class);

        if ($ancestorHasFilament) {
            $this->logSkip($class, 'parent class uses Filament trait — add HasFluentValidationForFilament with insteadof directly on this class if needed (rector cannot safely auto-compose through inheritance)');

            return null;
        }

        $isFilament = $filamentTrait instanceof Name;
        $target = $isFilament ? 'HasFluentValidationForFilament' : 'HasFluentValidation';
        $wrong = $isFilament ? 'HasFluentValidation' : 'HasFluentValidationForFilament';
        $targetFqn = $isFilament ? HasFluentValidationForFilament::class : HasFluentValidation::class;
        $wrongFqn = $isFilament ? HasFluentValidation::class : HasFluentValidationForFilament::class;

        if ($this->directlyUsesTrait($class, $targetFqn)) {
            $this->logSkip($class, sprintf('already has %s trait', $target), verboseOnly: true, actionable: false);

            return null;
        }

        if ($this->anyAncestorUsesTrait($class, $targetFqn)) {
            $this->logSkip($class, sprintf('parent class already uses %s (trait inherited)', $target), verboseOnly: true, actionable: false);

            return null;
        }

        if ($this->conflictsWithExplicitMethodOverride($class, $target)) {
            $this->logSkip($class, sprintf(
                'class declares %s directly — %s insertion would be pre-empted by class-level method resolution',
                $target === 'HasFluentValidation'
                    ? 'validate() or validateOnly()'
                    : 'one of validate()/validateOnly()/getRules()/getValidationAttributes()',
                $target,
            ));

            return null;
        }

        $wrongTrait = $this->directlyUsesTrait($class, $wrongFqn) ? $wrong : null;

        if ($wrongTrait === null && $this->anyAncestorUsesTrait($class, $wrongFqn)) {
            $this->logSkip($class, sprintf(
                'parent class uses %s but this class needs %s — manual fix required',
                $wrong,
                $target,
            ));

            return null;
        }

        return [
            'target' => $target,
            'filament_trait' => $filamentTrait,
            'wrong_trait' => $wrongTrait,
        ];
    }

    /**
     * @param  array{target: 'HasFluentValidation'|'HasFluentValidationForFilament', filament_trait: Name|null, wrong_trait: string|null}  $action
     */
    private function applyAction(Class_ $class, array $action): void
    {
        if ($action['wrong_trait'] !== null) {
            $this->removeDirectTraitUse($class, $action['wrong_trait']);
        }

        $adaptations = $action['filament_trait'] instanceof Name
            ? $this->buildFilamentInsteadofAdaptations($action['filament_trait'])
            : [];

        $this->insertTraitUseInClass($class, $action['target'], $adaptations);
    }

    /**
     * Build the 4-method `insteadof` adaptation block that resolves the
     * composition collision between `HasFluentValidationForFilament` and
     * Filament's `InteractsWithForms` / `InteractsWithSchemas`. The Filament
     * trait Name node is cloned per entry so each `Precedence` carries its
     * own AST subtree.
     *
     * @return list<Precedence>
     */
    private function buildFilamentInsteadofAdaptations(Name $filamentTrait): array
    {
        $adaptations = [];

        foreach (self::FILAMENT_INSTEADOF_METHODS as $methodName) {
            $adaptations[] = new Precedence(
                new Name('HasFluentValidationForFilament'),
                new Identifier($methodName),
                [clone $filamentTrait],
            );
        }

        return $adaptations;
    }

    /**
     * True when the class itself declares a method that would pre-empt the
     * trait's override via PHP's "class > trait" method resolution.
     * For HasFluentValidation (1.8.1): validate / validateOnly.
     * For HasFluentValidationForFilament (1.8.1): validate / validateOnly /
     * getRules / getValidationAttributes — the 4 methods the trait overrides.
     */
    private function conflictsWithExplicitMethodOverride(Class_ $class, string $target): bool
    {
        $blockingNames = $target === 'HasFluentValidation'
            ? ['validate', 'validateOnly']
            : self::FILAMENT_INSTEADOF_METHODS;

        foreach ($class->getMethods() as $method) {
            foreach ($blockingNames as $name) {
                if ($this->isName($method, $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Abstract Livewire bases with no `rules()` method have no validation
     * surface — silently skip. When they do declare `rules()`, subclasses
     * may override it, so the correct place for the trait is on each
     * concrete subclass; emit an actionable skip to guide the user.
     */
    private function logAbstractLivewireSkip(Class_ $class): void
    {
        if (! $this->hasRulesMethod($class)) {
            return;
        }

        $this->logSkip($class, 'abstract class with rules() (subclasses may override; add the trait directly on concrete subclasses)');
    }

    private function hasRulesMethod(Class_ $class): bool
    {
        foreach ($class->getMethods() as $method) {
            if ($this->isName($method, 'rules')) {
                return true;
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
