<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitor;
use Rector\Rector\AbstractRector;
use ReflectionClass;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidation\HasFluentValidationForFilament;
use SanderMuller\FluentValidationRector\Rector\Concerns\DetectsInheritedTraits;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;
use SanderMuller\FluentValidationRector\Rector\Concerns\NormalizesRulesDocblock;
use SanderMuller\FluentValidationRector\RunSummary;
use SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\UpdateRulesReturnTypeDocblockRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Opt-in polish rule: narrows the `@return` PHPDoc annotation on `rules()`
 * methods to `array<string, FluentRuleContract>` when the returned array is
 * composed entirely of `FluentRule::*()` call chains.
 *
 * Activates only inside the POLISH set. Runtime behavior is untouched; only
 * the docblock shape changes, giving downstream static analyzers a narrower
 * type than the `ValidationRule|string|array<mixed>` wide-union the
 * conversion rectors emit mid-pipeline.
 *
 * @see UpdateRulesReturnTypeDocblockRectorTest
 */
final class UpdateRulesReturnTypeDocblockRector extends AbstractRector implements DocumentedRuleInterface
{
    use DetectsInheritedTraits;
    use LogsSkipReasons;
    use NormalizesRulesDocblock;

    /**
     * Final emitted `@return` annotation body. Leading backslash on the FQN
     * matches `STANDARD_RULES_ANNOTATION_BODY`'s style so both annotations
     * read consistently in a narrowed file. Hard-coded as a string so the
     * rector runs regardless of which package version the consumer has
     * installed — the contract is emitted into docblocks as text, not
     * resolved as a PHP class at rector-time.
     */
    private const string CONTRACT_ANNOTATION_BODY = 'array<string, \\SanderMuller\\FluentValidation\\Contracts\\FluentRuleContract>';

    /**
     * FQN list of traits that qualify a class for polish (condition 5). These
     * are the three traits shipped by the fluent-validation package that
     * replace `FormRequest` inheritance.
     *
     * @var list<string>
     */
    private const array QUALIFYING_TRAIT_FQNS = [
        HasFluentRules::class,
        HasFluentValidation::class,
        HasFluentValidationForFilament::class,
    ];

    public function __construct()
    {
        RunSummary::registerShutdownHandler();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Narrow the @return annotation on rules() to array<string, FluentRuleContract> when every value is a FluentRule call chain.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class StorePostRequest extends FormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|string|array<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
            'body'  => FluentRule::string()->nullable(),
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
    /**
     * @return array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
            'body'  => FluentRule::string()->nullable(),
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

        if (! $this->classQualifiesForPolish($node)) {
            // Silent skip — logging here would spam for every unrelated class
            // in the codebase. Class-context is the qualifying gate; only
            // skips INSIDE a qualifying class are interesting signal.
            return null;
        }

        $hasChanged = false;

        foreach ($node->getMethods() as $method) {
            if (! $this->isName($method, 'rules')) {
                continue;
            }

            if ($this->processRulesMethod($node, $method)) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $node : null;
    }

    private function classQualifiesForPolish(Class_ $class): bool
    {
        if ($this->anyAncestorExtends($class, FormRequest::class)) {
            return true;
        }

        foreach (self::QUALIFYING_TRAIT_FQNS as $traitFqn) {
            if ($this->currentOrAncestorUsesTrait($class, $traitFqn)) {
                return true;
            }
        }

        return false;
    }

    private function processRulesMethod(Class_ $class, ClassMethod $method): bool
    {
        if (! $this->hasQualifyingReturnType($class, $method)) {
            return false;
        }

        $arrayNode = $this->singleLiteralArrayReturn($class, $method);

        if (! $arrayNode instanceof Array_) {
            return false;
        }

        if (! $this->allItemsAreFluentChains($class, $arrayNode)) {
            return false;
        }

        if (! $this->docblockIsNarrowable($class, $method)) {
            return false;
        }

        return $this->emitContractAnnotation($method);
    }

    private function hasQualifyingReturnType(Class_ $class, ClassMethod $method): bool
    {
        if ($method->returnType instanceof NullableType) {
            $this->logSkip($class, 'method rules() has nullable array return — cannot narrow contract');

            return false;
        }

        // Non-array return types are out of scope — silent skip, not polish material.
        return $method->returnType instanceof Identifier && $method->returnType->toLowerString() === 'array';
    }

    private function singleLiteralArrayReturn(Class_ $class, ClassMethod $method): ?Array_
    {
        $returns = [];
        $this->traverseNodesWithCallable($method->stmts ?? [], function (Node $node) use (&$returns): ?int {
            if ($node instanceof FunctionLike) {
                return NodeVisitor::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
            }

            if ($node instanceof Return_) {
                $returns[] = $node;
            }

            return null;
        });

        if (count($returns) !== 1) {
            $this->logSkip($class, 'method rules() has multi-stmt return — cannot narrow contract');

            return null;
        }

        $return = $returns[0];

        if (! $return->expr instanceof Array_) {
            $shape = $return->expr instanceof Expr
                ? (new ReflectionClass($return->expr))->getShortName()
                : 'null';
            $this->logSkip($class, sprintf('return expression is %s, not Array_ literal', $shape));

            return null;
        }

        return $return->expr;
    }

    private function allItemsAreFluentChains(Class_ $class, Array_ $arrayNode): bool
    {
        foreach ($arrayNode->items as $index => $item) {
            if (! $item instanceof ArrayItem) {
                $this->logSkip($class, sprintf('ArrayItem at index %d is not a plain ArrayItem (spread or malformed)', $index));

                return false;
            }

            if (! $item->key instanceof Expr || (! $item->key instanceof String_ && ! $item->key instanceof ClassConstFetch)) {
                $this->logSkip($class, sprintf('ArrayItem key at index %d is not String_ / ClassConstFetch', $index));

                return false;
            }

            if (! $this->isFluentRuleChainValue($item->value)) {
                $shape = (new ReflectionClass($item->value))->getShortName();
                $keyDesc = $item->key instanceof String_ ? $item->key->value : 'const';
                $this->logSkip($class, sprintf("value at key '%s' is not a FluentRule chain (shape: %s)", $keyDesc, $shape));

                return false;
            }
        }

        return true;
    }

    private function docblockIsNarrowable(Class_ $class, ClassMethod $method): bool
    {
        $doc = $method->getDocComment();

        if ($doc instanceof Doc && stripos($doc->getText(), '@inheritdoc') !== false) {
            // Case-insensitive: PHPDoc spec allows both `@inheritDoc` (canonical)
            // and `@inheritdoc` (common lowercase variant).
            $this->logSkip($class, 'docblock contains @inheritDoc — respecting parent contract');

            return false;
        }

        $existingBody = $this->extractReturnAnnotationBody($doc);

        if ($existingBody === null) {
            return true;
        }

        if (! $this->canNarrowExistingBody($existingBody)) {
            $truncated = substr(trim($existingBody), 0, 80);
            $this->logSkip($class, sprintf("existing @return tag '%s' is user-customized — respecting", $truncated));

            return false;
        }

        // Already narrowed — idempotency guard, silent.
        return trim($existingBody) !== self::CONTRACT_ANNOTATION_BODY;
    }

    private function isFluentRuleChainValue(Expr $value): bool
    {
        $current = $value;

        while ($current instanceof MethodCall) {
            $current = $current->var;
        }

        if (! $current instanceof StaticCall) {
            return false;
        }

        $className = $this->getName($current->class);

        return $className === FluentRule::class;
    }

    /**
     * Returns the raw (joined, un-trimmed) body of the first `@return` tag,
     * or `null` when the docblock is missing the tag entirely. Continuation
     * lines are folded into a single-line string so the body can be compared
     * against `CONTRACT_ANNOTATION_BODY` / `STANDARD_RULES_ANNOTATION_BODY`
     * without whitespace fighting.
     */
    private function extractReturnAnnotationBody(?Doc $doc): ?string
    {
        if (! $doc instanceof Doc) {
            return null;
        }

        if (preg_match(self::RETURN_TAG_PATTERN, $doc->getText(), $matches) !== 1) {
            return null;
        }

        $body = (string) preg_replace('/\s*\n[ \t]*\*\s*/', ' ', $matches[2]);

        // A wrapped `@return` whose type starts on the next line leaves the
        // captured body with a leading `* ` (the continuation-line asterisk
        // the preceding `\s+` didn't consume). Strip so the body compares
        // cleanly against `STANDARD_RULES_ANNOTATION_BODY`.
        return (string) preg_replace('/^\s*\*\s*/', '', $body);
    }

    private function canNarrowExistingBody(string $body): bool
    {
        $trimmed = trim($body);

        if ($trimmed === 'array') {
            return true;
        }

        return $this->annotationBodyMatchesStandardUnionExactlyOrProse($body);
    }

    private function emitContractAnnotation(ClassMethod $method): bool
    {
        $doc = $method->getDocComment();
        $contractTag = '@return ' . self::CONTRACT_ANNOTATION_BODY;

        if (! $doc instanceof Doc) {
            $method->setDocComment(new Doc(sprintf("/**\n * %s\n */", $contractTag)));

            return true;
        }

        $text = $doc->getText();

        if (preg_match(self::RETURN_TAG_PATTERN, $text) === 1) {
            $replaced = (string) preg_replace(
                self::RETURN_TAG_PATTERN,
                '$1 ' . self::CONTRACT_ANNOTATION_BODY,
                $text,
                1,
            );

            if ($replaced === $text) {
                return false;
            }

            $method->setDocComment(new Doc($replaced));

            return true;
        }

        // Docblock exists but has no @return tag — inject one before the
        // closing `*/`. Preserve existing content verbatim.
        //
        // Single-line shape `/** content */` can't be injected with a simple
        // `*/` replacement — doing so produces `/** content * @return ... */`
        // with the new tag inline with the original content. Expand single-line
        // to multi-line first, then fall through to the multi-line path.
        if (! str_contains($text, "\n")) {
            $text = $this->expandSingleLineDocblockToMultiline($text);
        }

        $injected = preg_replace(
            '/([ \t]*)\*\/\s*$/',
            "$1* {$contractTag}\n$1*/",
            $text,
            1,
        );

        if (! is_string($injected) || $injected === $text) {
            return false;
        }

        $method->setDocComment(new Doc($injected));

        return true;
    }

    /**
     * Converts `/** content *\/` to
     *
     *     /**
     *      * content
     *      *\/
     *
     * preserving the content verbatim. The inject path relies on matching the
     * trailing `*\/` on its own line; single-line docblocks don't have that,
     * so we expand them first. Content stripped of leading `/**` and trailing
     * `*\/` markers, with inner whitespace trimmed to avoid double-spacing.
     */
    private function expandSingleLineDocblockToMultiline(string $singleLine): string
    {
        $content = preg_replace('/^\/\*\*\s*|\s*\*\/$/', '', $singleLine);
        $content = trim((string) $content);

        if ($content === '') {
            return "/**\n */";
        }

        return "/**\n * {$content}\n */";
    }
}
