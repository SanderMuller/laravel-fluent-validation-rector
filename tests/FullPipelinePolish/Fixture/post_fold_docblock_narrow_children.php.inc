<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipeline\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.1 P1 multi-rector reproduction fixture: '*.' . CONST input
// flows through GroupWildcardRulesToEachRector (folds it into
// '*' => FluentRule::array()->children([...])) AND
// UpdateRulesReturnTypeDocblockRector (should narrow the @return
// to array<string, FluentRuleContract>). Static-input fixture
// at tests/UpdateRulesReturnTypeDocblock/Fixture/narrows_after_groupwildcard_fold_children.php.inc
// passes — predicate accepts the shape. Pinning post-pipeline
// state here; once the multi-pass narrow lands, expected flips.
final class PostFoldDocblockNarrowRequest extends FormRequest
{
    public const string NAME = 'name';

    public const string COLOR = 'color';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '*.' . self::NAME => FluentRule::string()->required(),
            '*.' . self::COLOR => FluentRule::string()->nullable(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipeline\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// 0.19.1 P1 multi-rector reproduction fixture: '*.' . CONST input
// flows through GroupWildcardRulesToEachRector (folds it into
// '*' => FluentRule::array()->children([...])) AND
// UpdateRulesReturnTypeDocblockRector (should narrow the @return
// to array<string, FluentRuleContract>). Static-input fixture
// at tests/UpdateRulesReturnTypeDocblock/Fixture/narrows_after_groupwildcard_fold_children.php.inc
// passes — predicate accepts the shape. Pinning post-pipeline
// state here; once the multi-pass narrow lands, expected flips.
final class PostFoldDocblockNarrowRequest extends FormRequest
{
    use HasFluentRules;

    public const string NAME = 'name';

    public const string COLOR = 'color';

    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return ['*' => FluentRule::array()->children([
            self::NAME => FluentRule::string()->required(),
            self::COLOR => FluentRule::string()->nullable(),
        ])];
    }
}

?>
