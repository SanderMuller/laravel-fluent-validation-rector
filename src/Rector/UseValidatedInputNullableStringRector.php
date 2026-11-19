<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Cast\String_ as StringCast;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Type\NullType;
use PHPStan\Type\StringType;
use PHPStan\Type\UnionType;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidationRector\Rector\Concerns\MatchesValidatedInputReads;
use SanderMuller\FluentValidationRector\Rector\Concerns\ShortCircuitsIrrelevantFiles;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\UseValidatedInputNullableStringRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rewrites `$safe->filled('k') ? $safe->string('k')->value() : $fallback` to
 * `$safe->nullableString('k', $fallback)` when `$safe` is the package
 * `ValidatedInput`.
 *
 * Accepted reads: `string()` or `str()`, then `->value()`, `->toString()`,
 * `->__toString()`, a `(string)` cast or `strval()`. Accepted conditions:
 * `filled('k')`, and the inverted `! filled('k')` / `isNotFilled('k')` with
 * the branches swapped.
 *
 * The fallback must be typed `string|null` and be safe to read eagerly: a
 * literal, a constant or a certainly defined variable. The ternary reads it
 * only when the field is blank, the call reads it every time.
 *
 * Known difference: `filled()` accepts a bool, an array and an object (for
 * example an uploaded file). For those, `string()->value()` gives '1' or ''
 * (bool), 'Array' with an "Array to string conversion" warning (array), or the
 * string form of the object (the path, for a file), and `nullableString()`
 * gives the fallback. A field validated as a string cannot hold these values,
 * but the rule cannot see the validation rules, so it does not check this.
 * `RuntimeParityTest` pins both the matches and the differences.
 *
 * @see UseValidatedInputNullableStringRectorTest
 */
final class UseValidatedInputNullableStringRector extends AbstractRector implements DocumentedRuleInterface
{
    use MatchesValidatedInputReads;
    use ShortCircuitsIrrelevantFiles;

    private const array STRINGABLE_TERMINALS = ['value', 'tostring', '__tostring'];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace a filled() check around a string read with ValidatedInput::nullableString().',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$safe = $request->safe();

$title = $safe->filled('title') ? $safe->string('title')->value() : null;
$slug = $safe->filled('slug') ? $safe->string('slug')->toString() : 'untitled';
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$safe = $request->safe();

$title = $safe->nullableString('title');
$slug = $safe->nullableString('slug', 'untitled');
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
            $read = $whenTrue;
            $fallback = $whenFalse;
        } elseif ($this->methodNameIs($condition, 'isnotfilled')) {
            $read = $whenFalse;
            $fallback = $whenTrue;
        } else {
            return null;
        }

        $stringCall = $this->matchStringRead($read);

        if (! $stringCall instanceof MethodCall) {
            return null;
        }

        $conditionKey = $this->soleArgument($condition);
        $readKey = $this->soleArgument($stringCall);

        if (! $conditionKey instanceof Expr || ! $readKey instanceof Expr) {
            return null;
        }

        if (! $this->sameReceiverAndKey($condition, $conditionKey, $stringCall, $readKey)) {
            return null;
        }

        if (! $this->isPackageValidatedInput($condition->var) || ! $this->validatedInputHasMethod('nullableString')) {
            return null;
        }

        $args = [new Arg($conditionKey)];

        if (! $this->isNullConstant($fallback)) {
            if (! $this->isSafeEagerFallback($fallback) || ! $this->isNullableString($fallback)) {
                return null;
            }

            $args[] = new Arg($fallback);
        }

        return new MethodCall($condition->var, 'nullableString', $args);
    }

    /**
     * The `string('k')` / `str('k')` call inside a read that converts the
     * Stringable to a plain string, with no other Stringable call between.
     */
    private function matchStringRead(Expr $read): ?MethodCall
    {
        $stringable = null;

        if ($read instanceof StringCast) {
            $stringable = $read->expr;
        } elseif ($read instanceof FuncCall) {
            if ($read->name instanceof Name && strtolower((string) $this->getName($read)) === 'strval' && count($read->args) === 1 && $read->args[0] instanceof Arg && ! $read->args[0]->unpack && ! $read->args[0]->name instanceof Identifier) {
                $stringable = $read->args[0]->value;
            }
        } elseif ($read instanceof MethodCall && $read->args === []) {
            foreach (self::STRINGABLE_TERMINALS as $terminal) {
                if ($this->methodNameIs($read, $terminal)) {
                    $stringable = $read->var;

                    break;
                }
            }
        }

        if (! $stringable instanceof MethodCall) {
            return null;
        }

        if (! $this->methodNameIs($stringable, 'string') && ! $this->methodNameIs($stringable, 'str')) {
            return null;
        }

        return $stringable;
    }

    private function isNullableString(Expr $fallback): bool
    {
        return (new UnionType([new StringType(), new NullType()]))
            ->isSuperTypeOf($this->getType($fallback))
            ->yes();
    }
}
