<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidationRector\Rector\Concerns\ParsesParentRulesMethod;
use SanderMuller\FluentValidationRector\Rector\Concerns\ResolvesVariableSpread;
use SanderMuller\FluentValidationRector\Rector\Concerns\ShortCircuitsIrrelevantFiles;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Inline the parent's `rules()` array when a child `rules()` method spreads
 * `parent::rules()` at index 0. Unblocks the converter rectors, which
 * otherwise bail on spread items (can't statically determine the produced
 * keys).
 *
 * Applies only when the parent's `rules()` method is a simple
 * `return [...];` — any parent whose `rules()` merges, concatenates, or
 * performs method calls over its return stays spread (behaviour unchanged).
 *
 * Runs in the CONVERT set so the flattened array reaches
 * `ValidationString/ArrayToFluentRuleRector` for native fluent lowering.
 *
 * @see InlineResolvableParentRulesRectorTest
 */
final class InlineResolvableParentRulesRector extends AbstractRector implements DocumentedRuleInterface
{
    use ParsesParentRulesMethod;
    use ResolvesVariableSpread;
    use ShortCircuitsIrrelevantFiles;

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Inline parent::rules() at index 0 of child rules() when the parent returns a plain array literal, so the converter rectors can see the flat shape.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class ChildRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'foo' => 'required|string',
        ];
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
class ChildRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'parentKey' => '…',  // inlined from BaseRequest::rules()
            'foo' => 'required|string',
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
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        // File-level relevance gate. This rule only fires on classes
        // with `rules()` that spread `parent::rules()`. Both tokens are
        // cheap to grep before the AST walk.
        if (! $this->currentFileContainsAny(['parent::rules', 'function rules'])) {
            return null;
        }

        $rulesMethod = $node->getMethod('rules');

        if (! $rulesMethod instanceof ClassMethod) {
            return null;
        }

        $returnArray = $this->extractReturnArray($rulesMethod);

        if (! $returnArray instanceof Array_) {
            return null;
        }

        if ($returnArray->items === [] || ! isset($returnArray->items[0])) {
            return null;
        }

        $firstItem = $returnArray->items[0];

        if (! $firstItem instanceof ArrayItem) {
            return null;
        }

        if (! $firstItem->unpack) {
            return null;
        }

        $resolvedItems = $this->resolveSpreadSource($node, $rulesMethod, $firstItem->value);

        if ($resolvedItems === null) {
            return null;
        }

        $returnArray->items = [
            ...$resolvedItems,
            ...array_slice($returnArray->items, 1),
        ];

        // When the spread target was a Variable (variable-spread path),
        // the source assignment is dead after inlining — the gate
        // guaranteed exactly 2 references to the variable (1 write, 1
        // read in the spread), and the read is now gone. Leaving the
        // assignment in place would re-evaluate its RHS side effects
        // (method calls, `new`, post-inc/dec) AND duplicate the literal
        // items we just inlined into the return array. Strip the
        // `$var = ...;` stmt so the method body no longer runs that
        // expression twice. Codex review (2026-04-24) caught this.
        if ($firstItem->value instanceof Variable) {
            $this->stripDeadVariableSpreadAssign($rulesMethod, $firstItem->value);
        }

        return $node;
    }

    /**
     * Dispatch on the shape of the spread's value expression:
     *
     *   - `parent::rules()` — resolve through the parent class chain.
     *   - `Variable` — resolve via a top-level assignment in the same
     *     `rules()` method (literal array, or a further-resolvable RHS).
     *
     * Anything else (function calls, method calls, conditionals) is left
     * intact so the converter rectors continue to skip with their
     * existing "encountered spread at index N" bail.
     *
     * @return list<ArrayItem>|null
     */
    private function resolveSpreadSource(Class_ $class, ClassMethod $method, Expr $value): ?array
    {
        if ($this->isParentRulesStaticCall($value)) {
            return $this->resolveParentRulesItems($class);
        }

        if ($value instanceof Variable) {
            return $this->resolveVariableSpread($class, $method, $value);
        }

        return null;
    }

    /**
     * Accept one of two body shapes:
     *
     *   1. Single `return [...];`  — the original strict form, required
     *      when inlining INTO a parent (recursive path).
     *   2. N top-level `$x = <expr>;` statements followed by exactly one
     *      `return [...];` — the relaxed form that permits variable-spread
     *      at the call site. Any statement that isn't a plain
     *      `Expression(Assign)` (loops, conditionals, echos, further
     *      returns) rejects the whole method.
     *
     * Both shapes converge on the final `Return_` carrying an `Array_`
     * literal. Callers that need the strict form should pass
     * `$strict: true`.
     */
    private function extractReturnArray(ClassMethod $method, bool $strict = false): ?Array_
    {
        if ($method->stmts === null || $method->stmts === []) {
            return null;
        }

        if ($strict && count($method->stmts) !== 1) {
            return null;
        }

        $lastIndex = count($method->stmts) - 1;
        $returnStmt = $method->stmts[$lastIndex];

        if (! $returnStmt instanceof Return_) {
            return null;
        }

        for ($i = 0; $i < $lastIndex; ++$i) {
            $stmt = $method->stmts[$i];

            if (! $stmt instanceof Expression) {
                return null;
            }

            if (! $stmt->expr instanceof Assign) {
                return null;
            }
        }

        return $returnStmt->expr instanceof Array_ ? $returnStmt->expr : null;
    }

    private function isParentRulesStaticCall(Node $node): bool
    {
        if (! $node instanceof StaticCall) {
            return false;
        }

        if (! $node->class instanceof Name) {
            return false;
        }

        if ($this->getName($node->class) !== 'parent') {
            return false;
        }

        if (! $node->name instanceof Identifier) {
            return false;
        }

        return $node->name->toString() === 'rules';
    }

    /**
     * Walk the class's parent chain via native `ReflectionClass`, locate
     * the concrete `rules()` declaration, parse that source file, and
     * return the cloned array items ready to splice. Returns null on any
     * bail — keeps the child's spread intact for safety.
     *
     * The earlier iteration of this method used PHPStan's `Scope`-carried
     * `ClassReflection`. That silently returned null for any child whose
     * file was outside the composer autoloader's PSR-4 scanned roots —
     * including every non-first fixture `.inc` class, plus any consumer
     * codebase where rector runs against a directory not listed in
     * `autoload.psr-4`. Only the PARENT needs to be loadable for native
     * reflection to work, which is almost always satisfied (FormRequest
     * bases sit under standard app/ PSR-4).
     *
     * @return list<ArrayItem>|null
     */
    private function resolveParentRulesItems(Class_ $class): ?array
    {
        $parsedMethod = $this->resolveParentRulesMethod($class);

        if (! $parsedMethod instanceof ClassMethod) {
            return null;
        }

        // Parents must be `return [...];` only — pre-return assignments
        // in the parent body hold local state we'd silently drop if we
        // inlined just the return array into the child.
        $returnArray = $this->extractReturnArray($parsedMethod, strict: true);

        if (! $returnArray instanceof Array_) {
            return null;
        }

        $items = [];

        foreach ($returnArray->items as $item) {
            if (! $item instanceof ArrayItem) {
                return null;
            }

            // Nested spreads inside the parent's return would themselves
            // need resolution; bail on any to keep this rector
            // single-hop and predictable.
            if ($item->unpack) {
                return null;
            }

            // `self::` / `static::` / `parent::` references rebind when the
            // item is inlined into the child: `self::FOO` that referred to
            // the PARENT class now resolves against the CHILD, silently
            // changing keys (or fataling when the constant/method doesn't
            // exist there). Bail on any such reference — users who need this
            // case can refactor to a plain literal or use FQCN constants.
            if ($this->containsClassRelativeReference($item)) {
                return null;
            }

            $items[] = $this->cloneArrayItem($item);
        }

        return $items;
    }

    /**
     * Scan an ArrayItem subtree for `self::` / `static::` / `parent::`
     * references. Any of these would rebind to the child's class after
     * inlining and change semantics.
     */
    private function containsClassRelativeReference(ArrayItem $item): bool
    {
        $visitor = new class extends NodeVisitorAbstract {
            public bool $found = false;

            public function enterNode(Node $node): ?int
            {
                if (! $node instanceof Name) {
                    return null;
                }

                $lower = strtolower($node->toString());

                if (in_array($lower, ['self', 'static', 'parent'], true)) {
                    $this->found = true;

                    return NodeVisitor::STOP_TRAVERSAL;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse([$item]);

        return $visitor->found;
    }

    /**
     * Clone an ArrayItem deeply so mutating the inlined entries in the
     * child's AST can't bleed back into the parent's in-memory cache or
     * surprise the Rector format-preserving printer on subsequent files.
     *
     * The second visitor strips the token-position attributes the
     * format-preserving printer uses to decide "this node came from the
     * source file at positions X..Y, print it verbatim". Items here came
     * from the parent's source file — those positions are meaningless in
     * the child's file and cause garbage output (`::rules(),,` observed in
     * the first implementation). Clearing the attributes forces the
     * printer into pretty-print mode for each inlined item.
     */
    private function cloneArrayItem(ArrayItem $item): ArrayItem
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CloningVisitor());
        $traverser->addVisitor(new class extends NodeVisitorAbstract {
            public function enterNode(Node $node): Node
            {
                $node->setAttribute('startTokenPos', -1);
                $node->setAttribute('endTokenPos', -1);
                $node->setAttribute('startFilePos', -1);
                $node->setAttribute('endFilePos', -1);
                $node->setAttribute('origNode', null);

                return $node;
            }
        });

        $cloned = $traverser->traverse([$item]);

        /** @var ArrayItem $first */
        $first = $cloned[0];

        return $first;
    }
}
