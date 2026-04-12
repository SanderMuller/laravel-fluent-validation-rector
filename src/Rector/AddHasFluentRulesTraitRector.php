<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeVisitor;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PostRector\Collector\UseNodesToAddCollector;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
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
    use LogsSkipReasons;

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

    public function __construct(private readonly UseNodesToAddCollector $useNodesToAddCollector) {}

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
        return [ClassLike::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        // Skip Livewire classes — they use HasFluentValidation, not HasFluentRules
        if ($this->isLivewireClass($node)) {
            $this->logSkip($node, 'detected as Livewire (uses HasFluentValidation instead)');

            return null;
        }

        if ($this->alreadyHasTrait($node)) {
            $this->logSkip($node, 'already has HasFluentRules trait');

            return null;
        }

        $className = $node->namespacedName instanceof Name ? $node->namespacedName->toString() : null;
        $isConfiguredBase = $className !== null && in_array($className, $this->baseClasses, true);

        // Configured base classes always get the trait (even if abstract or no FluentRule usage)
        if ($isConfiguredBase) {
            $this->addTraitToClass($node);

            return $node;
        }

        // Children of configured base classes inherit the trait — skip to avoid duplication
        if ($node->extends instanceof Name && in_array($this->getName($node->extends), $this->baseClasses, true)) {
            $this->logSkip($node, 'extends a configured base class (trait inherited from parent)');

            return null;
        }

        // Skip abstract classes — subclasses may transform rules via mergeRecursive
        if ($node->isAbstract()) {
            $this->logSkip($node, 'abstract class (subclasses may transform rules via mergeRecursive)');

            return null;
        }

        if (! $this->usesFluentRule($node)) {
            $this->logSkip($node, 'no FluentRule usage in rules() method');

            return null;
        }

        $this->addTraitToClass($node);

        return $node;
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
        // Queue a top-of-file `use SanderMuller\FluentValidation\HasFluentRules;`
        // import and emit the short name inside the class. Relying on consumers to
        // enable `withImportNames()` (or Pint) to clean up an FQN-inline trait use
        // leaves the rector's out-of-the-box output unpolished, so the rector now
        // handles the import itself via Rector's post-rector pipeline.
        $this->useNodesToAddCollector->addUseImport(new FullyQualifiedObjectType(HasFluentRules::class));

        $traitUse = new TraitUse([new Name('HasFluentRules')]);

        // Insert after existing trait uses, or at the beginning of the class body
        $insertPosition = 0;

        foreach ($class->stmts as $i => $stmt) {
            if ($stmt instanceof TraitUse) {
                $insertPosition = $i + 1;
            }
        }

        // Emit a blank line (Nop) between the trait and the next member when the
        // inserted trait would otherwise sit flush against a non-trait statement.
        // Pint's `class_attributes_separation` has a `trait_import` key but it's
        // opt-in, so the rector produces properly spaced output on its own.
        // Skip the Nop when the original stmts already have a blank-line gap —
        // the format-preserving printer keeps that gap, so adding a Nop would
        // double it.
        $toInsert = $this->needsBlankLineAfterTrait($class, $insertPosition)
            ? [$traitUse, new Nop()]
            : [$traitUse];

        array_splice($class->stmts, $insertPosition, 0, $toInsert);
    }

    private function needsBlankLineAfterTrait(Class_ $class, int $insertPosition): bool
    {
        $nextStmt = $class->stmts[$insertPosition] ?? null;

        if ($nextStmt === null) {
            return false;
        }

        if ($nextStmt instanceof TraitUse || $nextStmt instanceof Nop) {
            return false;
        }

        // If the previous statement (another trait) is flush against the next
        // statement, insert a Nop. Otherwise the original gap is preserved by
        // the format-preserving printer and a Nop would stack on top of it.
        $prevStmt = $insertPosition > 0 ? $class->stmts[$insertPosition - 1] : null;

        if ($prevStmt === null) {
            return true;
        }

        $prevEnd = $prevStmt->getEndLine();
        $comments = $nextStmt->getComments();
        $nextStart = $comments === [] ? $nextStmt->getStartLine() : $comments[0]->getStartLine();

        return $nextStart - $prevEnd < 2;
    }
}
