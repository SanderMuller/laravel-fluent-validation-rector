<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipStringMinFloatIntOnly
{
    public function rules(): array
    {
        // StringRule::min/max/between/exactly are int-only signatures.
        // Laravel's `min:1.5` on a string rule is valid (size check via
        // BigNumber comparison), but `->min(1.5)` would TypeError. Refuse
        // to rewrite — leave as escape hatch.
        return [
            'a' => FluentRule::string()->rule('min:1.5'),
            'b' => FluentRule::array()->rule('max:0.25'),
            'c' => FluentRule::password()->rule('min:8.5'),
            'd' => FluentRule::email()->rule('max:255.5'),
        ];
    }
}

?>
