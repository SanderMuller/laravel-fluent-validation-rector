<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipEmailMinBetweenUnsupported
{
    public function rules(): array
    {
        // EmailRule has `max` but not `min`/`between`/`exactly` — these
        // must stay as escape-hatch ->rule(...).
        return [
            'a' => FluentRule::email()->rule('min:5'),
            'b' => FluentRule::email()->rule('between:5,255'),
            'c' => FluentRule::email()->rule('size:64'),
            // boolean has none of these
            'd' => FluentRule::boolean()->rule('min:1'),
            // password has min but not exactly
            'e' => FluentRule::password()->rule('size:8'),
            // date has between but not exactly
            'f' => FluentRule::date()->rule('size:8'),
        ];
    }
}

?>
