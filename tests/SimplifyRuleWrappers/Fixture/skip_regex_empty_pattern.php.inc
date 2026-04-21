<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipRegexEmptyPattern
{
    public function rules(): array
    {
        // `'regex:'` and `['regex']` have no pattern — refuse to rewrite
        // to a `->regex('')` call that would runtime-error against
        // StringRule::regex's pattern requirement.
        return [
            'a' => FluentRule::string()->rule('regex:'),
            'b' => FluentRule::string()->rule(['regex']),
        ];
    }
}

?>
