<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipSignHelpersNonZeroOrField
{
    public function rules(): array
    {
        // Sign helpers fire only on literal `0` / `0.0`. Anything
        // else stays as the escape hatch: non-zero numeric literals
        // (`gt:5`), field comparisons (`gt:other_field`), broader
        // spellings (`gt:00`, `gt:-0`).
        return [
            'a' => FluentRule::numeric()->rule('gt:5'),
            'b' => FluentRule::numeric()->rule('gte:other_field'),
            'c' => FluentRule::numeric()->rule('gt:00'),
            'd' => FluentRule::numeric()->rule('gt:-0'),
            'e' => FluentRule::numeric()->rule(['gt', 5]),
            // Sign helpers don't exist on StringRule even with literal 0.
            'f' => FluentRule::string()->rule('gt:0'),
        ];
    }
}

?>
