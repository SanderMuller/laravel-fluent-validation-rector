<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipInEmptyTail
{
    public function rules(): array
    {
        // `'in:'` and `['in']` are degenerate inputs — refusing to invent
        // an empty-string member or empty-array call. Stay as escape hatch.
        return [
            'a' => FluentRule::string()->rule('in:'),
            'b' => FluentRule::string()->rule('in'),
            'c' => FluentRule::string()->rule(['in']),
        ];
    }
}

?>
