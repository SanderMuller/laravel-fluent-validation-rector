<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use SanderMuller\FluentValidation\FluentRule;

class SkipObjectRuleHopArrayParentNotPromoted extends \Illuminate\Foundation\Http\FormRequest
{
    private const string ITEMS = 'items';

    public function rules(): array
    {
        // A preserved object-valued `->rule(...)` hop reorders relative to the
        // seeded `array` constraint on ArrayRule (object rules serialize before
        // it), which could run the custom rule on non-array input. Only the
        // bare array rule is stripped, so this parent stays an escape hatch.
        return [
            self::ITEMS => FluentRule::field()
                ->bail()
                ->rule(new In(['a', 'b']))
                ->rule(Rule::array()),
            self::ITEMS . '.*' => FluentRule::string()
                ->max(255),
        ];
    }
}

?>
