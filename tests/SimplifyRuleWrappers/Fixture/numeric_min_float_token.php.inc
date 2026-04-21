<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class NumericMinFloatToken
{
    public function rules(): array
    {
        // NumericRule::min(int|float) requires a Float_ literal for
        // float-shaped tokens, not String_('1.5') (would type-error
        // at runtime).
        return [
            'a' => FluentRule::numeric()->rule('min:1.5'),
            'b' => FluentRule::numeric()->rule('between:-0.5,2.5'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class NumericMinFloatToken
{
    public function rules(): array
    {
        // NumericRule::min(int|float) requires a Float_ literal for
        // float-shaped tokens, not String_('1.5') (would type-error
        // at runtime).
        return [
            'a' => FluentRule::numeric()->min(1.5),
            'b' => FluentRule::numeric()->between(-0.5, 2.5),
        ];
    }
}

?>
