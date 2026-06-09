<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class SkipKeyedArrayRuleParentNotPromoted extends FormRequest
{
    private const string ITEMS = 'items';

    public function rules(): array
    {
        // A keyed `Rule::array(['a', 'b'])` carries `array:a,b` (required-keys)
        // semantics that the bare `array()` factory does not seed, so the
        // field() parent is NOT array-equivalent and the wildcard fold stays
        // an escape hatch.
        return [
            self::ITEMS => FluentRule::field()
                ->nullable()
                ->rule(Rule::array(['a', 'b'])),
            self::ITEMS . '.*' => FluentRule::string()
                ->nullable()
                ->max(255),
        ];
    }
}

?>
