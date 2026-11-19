<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Rector;

use Illuminate\Http\UploadedFile;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use SanderMuller\FluentValidationRector\Rector\Concerns\MatchesValidatedInputReads;
use SanderMuller\FluentValidationRector\Rector\Concerns\ShortCircuitsIrrelevantFiles;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\UseValidatedInputFileRectorTest;
use Symplify\RuleDocGenerator\Contract\DocumentedRuleInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rewrites `$safe->input('k') instanceof UploadedFile ? $safe->input('k') : null`
 * to `$safe->file('k')` when `$safe` is the package `ValidatedInput`. The
 * inverted `! (... instanceof UploadedFile) ? null : $safe->input('k')` is
 * accepted too.
 *
 * A non-null fallback becomes `$safe->file('k') ?? $fallback`: the same
 * result for any fallback, read only when there is no file, with the static
 * type of the original. `file('k', $fallback)` would return null for a
 * fallback that is not a file, and is typed `?UploadedFile`.
 *
 * @see UseValidatedInputFileRectorTest
 */
final class UseValidatedInputFileRector extends AbstractRector implements DocumentedRuleInterface
{
    use MatchesValidatedInputReads;
    use ShortCircuitsIrrelevantFiles;

    private const string UPLOADED_FILE_CLASS = UploadedFile::class;

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace an instanceof UploadedFile check around an input read with ValidatedInput::file().',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Illuminate\Http\UploadedFile;

$safe = $request->safe();

$avatar = $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar') : null;
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Illuminate\Http\UploadedFile;

$safe = $request->safe();

$avatar = $safe->file('avatar');
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
        if (! $this->currentFileContainsIgnoringCase('uploadedfile')) {
            return null;
        }

        $parts = $this->normalizeTernary($node);

        if ($parts === null) {
            return null;
        }

        [$condition, $read, $fallback] = $parts;

        if (! $condition instanceof Instanceof_ || ! $condition->class instanceof Name) {
            return null;
        }

        if (! $condition->expr instanceof MethodCall || ! $read instanceof MethodCall) {
            return null;
        }

        if (! $this->methodNameIs($condition->expr, 'input') || ! $this->methodNameIs($read, 'input')) {
            return null;
        }

        if (strcasecmp($this->getName($condition->class) ?? '', self::UPLOADED_FILE_CLASS) !== 0) {
            return null;
        }

        $conditionKey = $this->soleArgument($condition->expr);
        $readKey = $this->soleArgument($read);

        if (! $conditionKey instanceof Expr || ! $readKey instanceof Expr) {
            return null;
        }

        if (! $this->sameReceiverAndKey($condition->expr, $conditionKey, $read, $readKey)) {
            return null;
        }

        if (! $this->isPackageValidatedInput($read->var) || ! $this->validatedInputHasMethod('file')) {
            return null;
        }

        $file = new MethodCall($read->var, 'file', [new Arg($conditionKey)]);

        return $this->isNullConstant($fallback) ? $file : new Coalesce($file, $fallback);
    }
}
