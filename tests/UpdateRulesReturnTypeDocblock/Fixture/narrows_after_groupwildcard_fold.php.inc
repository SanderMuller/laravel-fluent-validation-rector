<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.18.0 reproducer: post-GroupWildcard-fold value
// `'*' => FluentRule::array()->each(...)` is a MethodCall whose
// receiver chain bottoms at the FluentRule::array() StaticCall. The
// docblock-narrow predicate walks MethodCall->var to the StaticCall
// root and matches FluentRule, so the @return narrows correctly.
// Pre-investigation peer report suggested the predicate was bailing
// on the MethodCall shape; turned out to be a separate issue (the
// fold didn't run on JsonAdaptive because GroupWildcard's gate
// fixed in 0.17.1 prevents the fold; the docblock-narrow path was
// fine).
class FoldedWildcardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '*' => FluentRule::array()->each(FluentRule::string()->max(255)),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.18.0 reproducer: post-GroupWildcard-fold value
// `'*' => FluentRule::array()->each(...)` is a MethodCall whose
// receiver chain bottoms at the FluentRule::array() StaticCall. The
// docblock-narrow predicate walks MethodCall->var to the StaticCall
// root and matches FluentRule, so the @return narrows correctly.
// Pre-investigation peer report suggested the predicate was bailing
// on the MethodCall shape; turned out to be a separate issue (the
// fold didn't run on JsonAdaptive because GroupWildcard's gate
// fixed in 0.17.1 prevents the fold; the docblock-narrow path was
// fine).
class FoldedWildcardRequest extends FormRequest
{
    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            '*' => FluentRule::array()->each(FluentRule::string()->max(255)),
        ];
    }
}

?>
