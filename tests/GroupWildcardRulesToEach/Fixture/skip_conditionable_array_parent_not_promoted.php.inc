<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipConditionableArrayParentNotPromoted extends \Illuminate\Foundation\Http\FormRequest
{
    private const string ITEMS = 'items';

    public function rules(): array
    {
        // A `when()`/`unless()`/`whenInput()` closure receives the rule builder;
        // after re-rooting to array() it would be an ArrayRule, and the closure
        // here calls the FieldRule-only `same()`. The fold stays an escape hatch.
        return [
            self::ITEMS => FluentRule::field()
                ->when(true, fn ($rule) => $rule->same('other'))
                ->rule('array'),
            self::ITEMS . '.*' => FluentRule::string()
                ->max(255),
        ];
    }
}

?>
