<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidationRector\Rector\Concerns\MatchesValidatedInputReads;
use SanderMuller\FluentValidationRector\Rector\Concerns\ShortCircuitsIrrelevantFiles;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\UseValidatedInputEnumRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rewrites `Status::tryFrom($safe->string('k')->value())` to
 * `$safe->enum('k', Status::class)`. Accepted reads are the same as for
 * `UseValidatedInputNullableStringRector`: `string()` or `str()`, then
 * `->value()`, `->toString()`, `->__toString()`, a `(string)` cast or
 * `strval()`.
 *
 * The enum must be string-backed, and no case value may be blank or numeric.
 * For a blank field, `enum()` returns null without calling `tryFrom()`, and
 * the original calls `tryFrom('')` (or the whitespace). For a bool, an int or
 * a float, `enum()` passes the raw value, which `tryFrom()` can read
 * differently from its string form: `false` as '0', `1.5` as '1'. Neither
 * difference can match a case that is not blank or numeric. An int-backed
 * enum is left alone: the original passes a string to `tryFrom()`, which can
 * throw under strict types.
 *
 * The receiver must be Laravel's `ValidatedInput` or a `Request`, without an
 * override of the methods involved or the helpers they call: both read input
 * through `InteractsWithData`.
 *
 * Known differences: for a float with a fraction, both give null, but
 * `enum()` also makes PHP log a "float to int" deprecation. For an array, the
 * original gives null with an "Array to string conversion" warning, and
 * `enum()` throws a `TypeError`. `Rule::enum()`, `string` and `in` validation
 * reject both values, so they reach the read only from unvalidated input or a
 * field validated without a type rule. The rule cannot see the validation.
 *
 * @see UseValidatedInputEnumRectorTest
 */
final class UseValidatedInputEnumRector extends AbstractRector implements DocumentedRuleInterface
{
    use MatchesValidatedInputReads;
    use ShortCircuitsIrrelevantFiles;

    /** The reads, the accessor, and the helpers they call. */
    private const array INPUT_METHODS = ['str', 'string', 'enum', 'data', 'isNotFilled', 'isEmptyString', 'isBackedEnum'];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace tryFrom() over a string read with the enum() input accessor.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$safe = $request->safe();

$status = Status::tryFrom($safe->string('status')->value());
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$safe = $request->safe();

$status = $safe->enum('status', Status::class);
CODE_SAMPLE,
                ),
            ],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    /**
     * @param  StaticCall  $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->currentFileContainsIgnoringCase('tryfrom')) {
            return null;
        }

        if (! $node->class instanceof Name || $node->class->isSpecialClassName() || ! $node->name instanceof Identifier || $node->name->toLowerString() !== 'tryfrom') {
            return null;
        }

        if (count($node->args) !== 1 || ! $node->args[0] instanceof Arg || $node->args[0]->unpack || $node->args[0]->name instanceof Identifier) {
            return null;
        }

        $stringCall = $this->matchStringRead($node->args[0]->value);

        if (! $stringCall instanceof MethodCall) {
            return null;
        }

        $key = $this->soleArgument($stringCall);

        if (! $key instanceof Expr || ! $this->isInputDataReceiver($stringCall->var, self::INPUT_METHODS)) {
            return null;
        }

        if (! $this->isStringBackedEnumWithoutBlankOrNumericCase($this->getName($node->class))) {
            return null;
        }

        return new MethodCall($stringCall->var, 'enum', [
            new Arg($key),
            new Arg(new ClassConstFetch($node->class, 'class')),
        ]);
    }

    private function isStringBackedEnumWithoutBlankOrNumericCase(?string $className): bool
    {
        if ($className === null || ! $this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        if (! $classReflection->isBackedEnum() || $classReflection->getBackedEnumType()?->isString()->yes() !== true) {
            return false;
        }

        foreach ($classReflection->getEnumCases() as $enumCase) {
            $values = $enumCase->getBackingValueType()?->getConstantStrings() ?? [];

            if (count($values) !== 1 || trim($values[0]->getValue()) === '' || is_numeric($values[0]->getValue())) {
                return false;
            }
        }

        return true;
    }
}
