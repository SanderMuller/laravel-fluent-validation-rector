<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitor;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidationRector\Internal\RunSummary;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\ManagesNamespaceImports;
use SanderMuller\FluentValidationRector\Rector\Concerns\ShortCircuitsIrrelevantFiles;
use SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\ConvertToFluentSchemaRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adopts the instance-based `schema(FluentSchema $rules)` builder shipped in
 * laravel-fluent-validation 1.31.0: rewrites a `rules()` method built from
 * `FluentRule::` static chains into a `schema()` method that receives a
 * `FluentSchema` builder, dropping the repeated `FluentRule::` prefix.
 *
 * Before:
 *
 *     public function rules(): array
 *     {
 *         return [
 *             'name' => FluentRule::string()->required()->max(255),
 *         ];
 *     }
 *
 * After:
 *
 *     public function schema(FluentSchema $rules): array
 *     {
 *         return [
 *             'name' => $rules->string()->required()->max(255),
 *         ];
 *     }
 *
 * Fires only on classes that use `HasFluentRules` (directly, via
 * `FluentFormRequest`, or via an ancestor). That trait's
 * `createDefaultValidator()` is the only runtime that dispatches a
 * `schema(FluentSchema)` method — a plain `FormRequest` without the trait, a
 * Livewire component (`HasFluentValidation` has no `schema()` hook), or a
 * Filament page would silently lose validation if `rules()` were renamed. This
 * is a stylistic opt-in and ships in its own `SCHEMA` set, never in `ALL`.
 *
 * @see ConvertToFluentSchemaRectorTest
 */
final class ConvertToFluentSchemaRector extends AbstractRector implements DocumentedRuleInterface
{
    use DetectsInheritedTraits;
    use LogsSkipReasons;
    use ManagesNamespaceImports;
    use ShortCircuitsIrrelevantFiles;

    /**
     * Candidate names for the injected `FluentSchema` parameter, in preference
     * order. `rules` is the convention; the rest are fallbacks for when the
     * body already uses that local (conditional-assembly `$rules` arrays).
     *
     * @var list<string>
     */
    private const array BUILDER_PARAM_CANDIDATES = ['rules', 'schema', 'rulesBuilder', 'fluentSchema', 'validationSchema'];

    public function __construct()
    {
        RunSummary::registerShutdownHandler();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert a FluentRule-based rules() method into the instance-based schema(FluentSchema $rules) builder.',
            [
                new CodeSample(
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
            'email' => FluentRule::email()->required(),
        ];
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

class StorePostRequest extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()->required()->max(255),
            'email' => $rules->email()->required(),
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

    /**
     * @param Namespace_ $node
     */
    public function refactor(Node $node): ?Node
    {
        // File-level relevance gate — a file with no FluentRule / rules()
        // surface has nothing for this rule to reshape.
        if (! $this->currentFileLooksRuleBearing()) {
            return null;
        }

        // Resolve the local name FluentSchema is (or will be) known by, so the
        // injected parameter type matches an existing aliased import
        // (`use ...FluentSchema as Schema;` → `schema(Schema $rules)`) instead
        // of a bare short name that would resolve to the current namespace.
        $schemaTypeName = $this->importedLocalName($node, FluentSchema::class) ?? 'FluentSchema';

        $changed = false;

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Class_) {
                continue;
            }

            if ($this->convertClass($stmt, $schemaTypeName)) {
                $changed = true;
            }
        }

        if (! $changed) {
            return null;
        }

        $this->ensureUseImportInNamespace($node, FluentSchema::class);

        // Drop the now-orphaned `use ...FluentRule;` only when no class in
        // the namespace still references the static factory (type hints,
        // `FluentRule::class`, or chains this rule couldn't convert).
        if (! $this->namespaceStillReferencesFluentRule($node)) {
            $this->removeUseImportFromNamespace($node, FluentRule::class);
        }

        return $node;
    }

    /**
     * Whether the class uses `HasFluentRules` (directly, via `FluentFormRequest`,
     * or an ancestor). Also accepts a bare short-name `use HasFluentRules;` that
     * `AddHasFluentRulesTraitRector` inserted earlier in the SAME pass — before
     * the name resolver attaches its FQCN — so an `ALL + SCHEMA` run converts a
     * plain FormRequest (string rules → FluentRule → trait → schema) end-to-end
     * rather than needing a second invocation. Mirrors the short-`FluentRule`
     * tolerance the converter rectors already use for the same reason.
     */
    private function usesHasFluentRulesTolerant(Class_ $class): bool
    {
        if ($this->currentOrAncestorUsesTrait($class, HasFluentRules::class)) {
            return true;
        }

        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $traitName) {
                if ($this->getName($traitName) === 'HasFluentRules') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the `rules()` method carries the `#[FluentRules]` attribute — the
     * per-method opt-in that also serves as the user's audit assertion for
     * abstract-class conversion (subclasses neither call `parent::rules()` nor
     * drop base keys). Matches by resolved FQN, so an aliased import still
     * counts and an unrelated same-short-name attribute does not.
     */
    private function rulesMethodHasFluentRulesAttribute(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->getName($attr->name) === FluentRules::class) {
                    return true;
                }
            }
        }

        return false;
    }

    private function convertClass(Class_ $class, string $schemaTypeName): bool
    {
        // The trait gate IS the safety boundary. `schema(FluentSchema)` is
        // dispatched only by HasFluentRules::createDefaultValidator(); on any
        // other class the renamed method is never called.
        if (! $this->usesHasFluentRulesTolerant($class)) {
            return false;
        }

        $method = $class->getMethod('rules');

        if (! $method instanceof ClassMethod) {
            return false;
        }

        if (! $this->rulesMethodIsConvertible($class, $method)) {
            return false;
        }

        if (! $this->methodBodyIsSafeToInjectBuilder($method)) {
            return false;
        }

        // Pick the injected builder's parameter name. `$rules` is the
        // convention, but the conditional-assembly pattern (`$rules = [...];
        // … return $rules;`) already uses that local — fall back to a free
        // name rather than skip. Only bails in the (essentially impossible)
        // case where every candidate name is already taken.
        $builderName = $this->resolveBuilderParamName($method);

        if ($builderName === null) {
            return false;
        }

        // Abstract classes need an explicit opt-in. The composer floor is
        // fluent-validation ^1.32, whose schema()/rules() merge makes the
        // common hazard safe: a subclass rules() override is merged with (not
        // shadowed by) the renamed base schema(), most-derived-wins. Two
        // residual rename hazards Rector can't prove per-file remain — a
        // subclass calling `parent::rules()` (now undefined) and an override
        // that drops a base key (the merge would reintroduce it). `#[FluentRules]`
        // on the rules() method is the user's audit assertion that neither
        // applies, mirroring how the converter rectors lift the abstract guard.
        // Refused (with an actionable log) only after confirming there IS a
        // convertible rules(), so it isn't noise on every abstract in the file.
        if ($class->isAbstract() && ! $this->rulesMethodHasFluentRulesAttribute($method)) {
            $this->logSkip(
                $class,
                'abstract class with a convertible rules() — the rename to schema() could break a subclass that calls parent::rules() or drops a base key. Add #[FluentRules] to the rules() method to assert subclass-safety and opt in (the ^1.32 schema()/rules() merge handles the override case).',
            );

            return false;
        }

        $this->rewriteFluentRuleStaticCalls($method, $builderName);
        $this->dropOverrideAttribute($method);

        $method->name = new Identifier('schema');
        $method->params[] = new Param(new Variable($builderName), type: new Name($schemaTypeName));

        return true;
    }

    /**
     * The builder parameter name to inject: `rules` when free, otherwise the
     * first unused fallback. Returns null only when every candidate is already
     * a variable in the method body.
     */
    private function resolveBuilderParamName(ClassMethod $method): ?string
    {
        $used = [];

        $this->traverseNodesWithCallable($method->stmts ?? [], function (Node $subNode) use (&$used): null {
            if ($subNode instanceof Variable && is_string($subNode->name)) {
                $used[$subNode->name] = true;
            }

            return null;
        });

        foreach (self::BUILDER_PARAM_CANDIDATES as $candidate) {
            if (! isset($used[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Drop a `#[\Override]` attribute from the method being renamed. `schema()`
     * is discovered reflectively by `HasFluentRules` and overrides no parent
     * method, so a carried-over `#[\Override]` (valid on a `rules()` that
     * overrode a base request's) would be a fatal at class load.
     */
    private function dropOverrideAttribute(ClassMethod $method): void
    {
        foreach ($method->attrGroups as $index => $attrGroup) {
            $attrGroup->attrs = array_values(array_filter(
                $attrGroup->attrs,
                fn (Attribute $attr): bool => $this->getName($attr->name) !== 'Override',
            ));

            if ($attrGroup->attrs === []) {
                unset($method->attrGroups[$index]);
            }
        }

        $method->attrGroups = array_values($method->attrGroups);
    }

    private function rulesMethodIsConvertible(Class_ $class, ClassMethod $method): bool
    {
        // schema() is resolved via `$this->container->call([$this, 'schema'])`,
        // so it must be a public, non-static instance method with a body.
        if (! $method->isPublic() || $method->isStatic() || $method->isAbstract() || $method->stmts === null) {
            return false;
        }

        // Laravel's rules() takes no parameters. A non-zero arity signals a
        // non-standard override this rule shouldn't reshape.
        if ($method->params !== []) {
            return false;
        }

        // Renaming rules() → schema() when a schema() already exists would
        // declare a duplicate method (fatal). Surface it instead.
        if ($class->getMethod('schema') instanceof ClassMethod) {
            $this->logSkip(
                $class,
                'Class already declares a schema() method — skipping the rules() → schema() rename to avoid a duplicate method declaration.',
            );

            return false;
        }

        return true;
    }

    /**
     * Confirm the injected builder would be in scope wherever a FluentRule call
     * is rewritten, and that at least one convertible call exists. Bails on:
     *
     * - a FluentRule chain built inside a nested scope the injected builder
     *   can't reach — a plain `function () { … }` closure (captures nothing
     *   unless the author wrote `use (...)`), an anonymous class, or a nested
     *   named function. Arrow functions auto-capture, so they stay safe;
     * - a dynamic `FluentRule::$method()` call that can't be mapped statically.
     *
     * A local variable clashing with the default `$rules` param name is NOT a
     * bail — `resolveBuilderParamName()` picks a free fallback name instead.
     */
    private function methodBodyIsSafeToInjectBuilder(ClassMethod $method): bool
    {
        $hasFluentRuleCall = false;
        $unsafe = false;

        $this->traverseNodesWithCallable($method->stmts ?? [], function (Node $subNode) use (&$hasFluentRuleCall, &$unsafe): ?int {
            // A FluentRule call inside a scope boundary that doesn't inherit
            // the injected $rules parameter: a plain closure, an anonymous
            // class, or a nested named function. (Arrow functions capture the
            // enclosing scope, so they are safe and deliberately not listed.)
            if (($subNode instanceof Closure || $subNode instanceof Class_ || $subNode instanceof Function_)
                && $this->containsFluentRuleStaticCall($subNode)) {
                $unsafe = true;

                return NodeVisitor::STOP_TRAVERSAL;
            }

            if ($subNode instanceof StaticCall && $this->isFluentRuleStaticCall($subNode)) {
                if (! $subNode->name instanceof Identifier) {
                    $unsafe = true;

                    return NodeVisitor::STOP_TRAVERSAL;
                }

                $hasFluentRuleCall = true;
            }

            return null;
        });

        return $hasFluentRuleCall && ! $unsafe;
    }

    private function rewriteFluentRuleStaticCalls(ClassMethod $method, string $builderName): void
    {
        $this->traverseNodesWithCallable($method->stmts ?? [], function (Node $subNode) use ($builderName): ?Node {
            if (! $subNode instanceof StaticCall || ! $this->isFluentRuleStaticCall($subNode)) {
                return null;
            }

            if (! $subNode->name instanceof Identifier) {
                return null;
            }

            // `FluentRule::string(...)` → `$<builder>->string(...)`. FluentSchema
            // mirrors every FluentRule factory 1:1 (and forwards macros via
            // __call), so the receiver swap preserves the produced rule.
            return new MethodCall(new Variable($builderName), $subNode->name, $subNode->args);
        });
    }

    private function containsFluentRuleStaticCall(Node $node): bool
    {
        $found = false;

        $this->traverseNodesWithCallable($node, function (Node $subNode) use (&$found): ?int {
            if ($subNode instanceof StaticCall && $this->isFluentRuleStaticCall($subNode)) {
                $found = true;

                return NodeVisitor::STOP_TRAVERSAL;
            }

            return null;
        });

        return $found;
    }

    private function isFluentRuleStaticCall(StaticCall $call): bool
    {
        $className = $this->getName($call->class);

        return $className === FluentRule::class || $className === 'FluentRule';
    }

    /**
     * Scan only the class bodies (never the `use` block, whose Name node would
     * otherwise register as a live reference) for any surviving mention of the
     * FluentRule factory.
     */
    private function namespaceStillReferencesFluentRule(Namespace_ $namespace): bool
    {
        foreach ($namespace->stmts as $stmt) {
            if (! $stmt instanceof Class_) {
                continue;
            }

            $found = false;

            $this->traverseNodesWithCallable($stmt, function (Node $subNode) use (&$found): ?int {
                if ($subNode instanceof Name
                    && ($this->getName($subNode) === FluentRule::class || $this->getName($subNode) === 'FluentRule')
                ) {
                    $found = true;

                    return NodeVisitor::STOP_TRAVERSAL;
                }

                return null;
            });

            if ($found) {
                return true;
            }
        }

        return false;
    }
}
