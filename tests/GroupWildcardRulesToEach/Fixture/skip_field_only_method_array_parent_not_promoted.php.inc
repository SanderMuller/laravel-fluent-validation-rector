<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class SkipFieldOnlyMethodArrayParentNotPromoted extends FormRequest
{
    private const string ITEMS = 'items';

    public function rules(): array
    {
        // `same()` is a FieldRule method absent from ArrayRule, so re-rooting
        // this parent to `array()->same('other')` would call an undefined
        // method. The parent stays on its escape hatch.
        return [
            self::ITEMS => FluentRule::field()
                ->same('other')
                ->rule(Rule::array()),
            self::ITEMS . '.*' => FluentRule::string()
                ->max(255),
        ];
    }
}

?>
