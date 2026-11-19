<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Ternary;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\UnionType;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidationRector\Rector\Concerns\MatchesValidatedInputReads;
use SanderMuller\FluentValidationRector\Rector\Concerns\ShortCircuitsIrrelevantFiles;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\UseValidatedInputNullableIntegerRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rewrites `$safe->filled('k') ? $safe->integer('k') : $fallback` to
 * `$safe->nullableInteger('k', $fallback)` when `$safe` is the package
 * `ValidatedInput`. `nullableInteger()` is that ternary, so the result is the
 * same for every value.
 *
 * Accepted conditions: `filled('k')`, and the inverted `! filled('k')` /
 * `isNotFilled('k')` with the branches swapped. The fallback must be typed
 * `int|null` and be safe to read eagerly: a literal, a constant or a certainly
 * defined variable. The ternary reads it only when the field is blank, the
 * call reads it every time.
 *
 * @see UseValidatedInputNullableIntegerRectorTest
 */
final class UseValidatedInputNullableIntegerRector extends AbstractRector implements DocumentedRuleInterface
{
    use MatchesValidatedInputReads;
    use ShortCircuitsIrrelevantFiles;

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace a filled() check around an integer() read with ValidatedInput::nullableInteger().',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$safe = $request->safe();

$rank = $safe->filled('rank') ? $safe->integer('rank') : null;
$limit = $safe->filled('limit') ? $safe->integer('limit') : 10;
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$safe = $request->safe();

$rank = $safe->nullableInteger('rank');
$limit = $safe->nullableInteger('limit', 10);
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
        return [Ternary::class];
    }

    /**
     * @param  Ternary  $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->currentFileContainsIgnoringCase('filled')) {
            return null;
        }

        $parts = $this->normalizeTernary($node);

        if ($parts === null) {
            return null;
        }

        [$condition, $whenTrue, $whenFalse] = $parts;

        if (! $condition instanceof MethodCall) {
            return null;
        }

        if ($this->methodNameIs($condition, 'filled')) {
            [$read, $fallback] = [$whenTrue, $whenFalse];
        } elseif ($this->methodNameIs($condition, 'isnotfilled')) {
            [$read, $fallback] = [$whenFalse, $whenTrue];
        } else {
            return null;
        }

        if (! $read instanceof MethodCall || ! $this->methodNameIs($read, 'integer')) {
            return null;
        }

        $conditionKey = $this->soleArgument($condition);
        $readKey = $this->soleArgument($read);

        if (! $conditionKey instanceof Expr || ! $readKey instanceof Expr) {
            return null;
        }

        if (! $this->sameReceiverAndKey($condition, $conditionKey, $read, $readKey)) {
            return null;
        }

        if (! $this->isPackageValidatedInput($condition->var) || ! $this->validatedInputHasMethod('nullableInteger')) {
            return null;
        }

        $args = [new Arg($conditionKey)];

        if (! $this->isNullConstant($fallback)) {
            if (! $this->isSafeEagerFallback($fallback) || ! $this->isNullableInteger($fallback)) {
                return null;
            }

            $args[] = new Arg($fallback);
        }

        return new MethodCall($condition->var, 'nullableInteger', $args);
    }

    private function isNullableInteger(Expr $fallback): bool
    {
        return (new UnionType([new IntegerType(), new NullType()]))
            ->isSuperTypeOf($this->getType($fallback))
            ->yes();
    }
}
