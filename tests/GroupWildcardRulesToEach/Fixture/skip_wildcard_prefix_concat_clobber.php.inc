<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 case (d) — clobber detection. A sibling non-wildcard `'*'`
// rule already exists; folding the wildcard-prefix-concat siblings
// into `'*' => array()->children([...])` would clobber it. Bail
// with the documented skip-log; consumer must move the `'*'` rule
// into the children body manually or rename the wildcard parent.
final class WildcardPrefixClobberRequest extends FormRequest
{
    public const string FIELD = 'field';

    public function rules(): array
    {
        return [
            '*' => FluentRule::array()->required(),
            '*.' . self::FIELD => FluentRule::string()->required(),
        ];
    }
}

?>
