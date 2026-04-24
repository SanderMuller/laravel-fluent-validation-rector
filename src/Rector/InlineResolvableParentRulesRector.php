<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Throwable;

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
    /**
     * Cache parsed parent files across rector invocations within one process
     * so repeated child classes sharing the same parent don't re-parse the
     * same source. Keyed by absolute file path + mtime; mtime invalidates
     * naturally when the parent file changes between runs.
     *
     * @var array<string, ClassMethod|false>
     */
    private static array $parentRulesCache = [];

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

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
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

        if (! $this->isParentRulesStaticCall($firstItem->value)) {
            return null;
        }

        $parentItems = $this->resolveParentRulesItems($node);

        if ($parentItems === null) {
            return null;
        }

        $returnArray->items = [
            ...$parentItems,
            ...array_slice($returnArray->items, 1),
        ];

        return $node;
    }

    private function extractReturnArray(ClassMethod $method): ?Array_
    {
        if ($method->stmts === null || count($method->stmts) !== 1) {
            return null;
        }

        $stmt = $method->stmts[0];

        if (! $stmt instanceof Return_) {
            return null;
        }

        return $stmt->expr instanceof Array_ ? $stmt->expr : null;
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
     * Reflect into the class's parent chain via PHPStan scope to locate the
     * concrete `rules()` declaration, parse that source file, and return the
     * cloned array items ready to splice. Returns null on any bail — keeps
     * the child's spread intact for safety.
     *
     * Uses PHPStan's `ClassReflection` instead of raw `ReflectionClass`
     * because the child class under test is frequently not autoloadable
     * (fixture files are `.inc`, excluded from PSR-4). PHPStan builds its
     * reflection from the AST + stubs, so it resolves the parent chain
     * even when `class_exists($childFqcn)` would return false.
     *
     * @return list<ArrayItem>|null
     */
    private function resolveParentRulesItems(Class_ $class): ?array
    {
        $scope = $class->getAttribute(AttributeKey::SCOPE);

        if (! $scope instanceof Scope) {
            return null;
        }

        $childReflection = $scope->getClassReflection();

        if (! $childReflection instanceof ClassReflection) {
            return null;
        }

        $parentReflection = $childReflection->getParentClass();

        if (! $parentReflection instanceof ClassReflection) {
            return null;
        }

        if (! $parentReflection->hasMethod('rules')) {
            return null;
        }

        $rulesMethod = $parentReflection->getMethod('rules', $scope);
        $declaringClass = $rulesMethod->getDeclaringClass();
        $fileName = $declaringClass->getFileName();

        if ($fileName === null) {
            return null;
        }

        $parsedMethod = $this->loadParentRulesMethod($fileName, $declaringClass->getNativeReflection()->getShortName());

        if (! $parsedMethod instanceof ClassMethod) {
            return null;
        }

        $returnArray = $this->extractReturnArray($parsedMethod);

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
     * Parse `$fileName`, locate the class with short name `$shortName`, and
     * return its `rules()` ClassMethod. Caches by (path, mtime) so repeated
     * children with the same parent pay the parse cost once.
     */
    private function loadParentRulesMethod(string $fileName, string $shortName): ?ClassMethod
    {
        $mtime = @filemtime($fileName);

        if ($mtime === false) {
            return null;
        }

        $cacheKey = $fileName . ':' . $mtime . ':' . $shortName;

        if (array_key_exists($cacheKey, self::$parentRulesCache)) {
            $cached = self::$parentRulesCache[$cacheKey];

            return $cached === false ? null : $cached;
        }

        $source = @file_get_contents($fileName);

        if ($source === false) {
            self::$parentRulesCache[$cacheKey] = false;

            return null;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $stmts = $parser->parse($source);
        } catch (Throwable) {
            self::$parentRulesCache[$cacheKey] = false;

            return null;
        }

        if ($stmts === null) {
            self::$parentRulesCache[$cacheKey] = false;

            return null;
        }

        $nodeFinder = new NodeFinder();

        $class = $nodeFinder->findFirst($stmts, static fn (Node $node): bool => $node instanceof Class_
            && $node->name instanceof Identifier
            && $node->name->toString() === $shortName);

        if (! $class instanceof Class_) {
            self::$parentRulesCache[$cacheKey] = false;

            return null;
        }

        $method = $class->getMethod('rules');

        if (! $method instanceof ClassMethod) {
            self::$parentRulesCache[$cacheKey] = false;

            return null;
        }

        self::$parentRulesCache[$cacheKey] = $method;

        return $method;
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
