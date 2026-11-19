<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidationRector\Rector\Concerns\MatchesValidatedInputReads;
use SanderMuller\FluentValidationRector\Rector\Concerns\ShortCircuitsIrrelevantFiles;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\UseValidatedInputDateRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rewrites `$safe->filled('k') ? $safe->date('k') : null` to
 * `$safe->date('k')`. `date()` already returns null for a blank field, so the
 * check changes nothing.
 *
 * Accepted conditions: `filled('k')`, and the inverted `isNotFilled('k')`
 * with the branches swapped. `has()` and `missing()` are left alone: they look
 * up the key with `Arr::has()`, which reads `*` and `{first}` differently from
 * the `data_get()` that `date()` uses. A format or timezone argument must be a
 * string literal, `null` or a certainly defined variable, because the ternary
 * reads it only when the field is filled.
 *
 * The receiver must be Laravel's `ValidatedInput` or a `Request`, without an
 * override of the methods involved or the helpers they call: both read input
 * through `InteractsWithData`.
 *
 * @see UseValidatedInputDateRectorTest
 */
final class UseValidatedInputDateRector extends AbstractRector implements DocumentedRuleInterface
{
    use MatchesValidatedInputReads;
    use ShortCircuitsIrrelevantFiles;

    /** The checks, the read, and the helpers they call. */
    private const array INPUT_METHODS = ['filled', 'isNotFilled', 'date', 'data', 'isEmptyString'];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove a filled() check around a date() read, which already returns null for a blank field.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$safe = $request->safe();

$publishedAt = $safe->filled('published_at') ? $safe->date('published_at') : null;
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$safe = $request->safe();

$publishedAt = $safe->date('published_at');
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
        if (! $this->currentFileContainsIgnoringCase('date')) {
            return null;
        }

        $parts = $this->normalizeTernary($node);

        if ($parts === null) {
            return null;
        }

        [$condition, $whenTrue, $whenFalse] = $parts;

        if (! $condition instanceof MethodCall || ! $condition->name instanceof Identifier) {
            return null;
        }

        $checkName = $condition->name->toLowerString();

        if ($checkName === 'filled') {
            [$read, $other] = [$whenTrue, $whenFalse];
        } elseif ($checkName === 'isnotfilled') {
            [$read, $other] = [$whenFalse, $whenTrue];
        } else {
            return null;
        }

        if (! $read instanceof MethodCall || ! $this->methodNameIs($read, 'date') || ! $this->isNullConstant($other)) {
            return null;
        }

        $conditionKey = $this->soleArgument($condition);
        $readKey = $this->dateKeyWithEagerSafeExtras($read);

        if (! $conditionKey instanceof Expr || ! $readKey instanceof Expr) {
            return null;
        }

        if (! $this->sameReceiverAndKey($condition, $conditionKey, $read, $readKey) || ! $this->isInputDataReceiver($read->var, self::INPUT_METHODS)) {
            return null;
        }

        return $read;
    }

    /**
     * The key of a `date('k', $format, $tz)` call whose arguments are all
     * positional and whose format and timezone cannot fail when read: a
     * string literal, `null` or a certainly defined variable. A constant can
     * be undefined.
     */
    private function dateKeyWithEagerSafeExtras(MethodCall $read): ?Expr
    {
        if ($read->args === [] || count($read->args) > 3) {
            return null;
        }

        foreach ($read->args as $position => $arg) {
            if (! $arg instanceof Arg || $arg->unpack || $arg->byRef || $arg->name instanceof Identifier) {
                return null;
            }

            if ($position > 0 && ! $this->isReadableWithoutFailure($arg->value)) {
                return null;
            }
        }

        return $read->args[0]->value;
    }

    private function isReadableWithoutFailure(Expr $expr): bool
    {
        if ($expr instanceof String_ || $this->isNullConstant($expr)) {
            return true;
        }

        return $expr instanceof Variable && $this->isSafeEagerFallback($expr);
    }
}
