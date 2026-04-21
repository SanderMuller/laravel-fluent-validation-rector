<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipRegexOnNonStringFactories
{
    public function rules(): array
    {
        // regex() exists only on StringRule. All other typed-rule classes
        // must keep the ->rule(...) escape hatch.
        return [
            'a' => FluentRule::numeric()->rule('regex:/^\d+$/'),
            'b' => FluentRule::array()->rule(['regex', '/^x+$/']),
            'c' => FluentRule::email()->rule('regex:/@example\.com$/'),
        ];
    }
}

?>
