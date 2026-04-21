<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipAlreadyNative
{
    public function rules(): array
    {
        // Already using native typed-rule methods — nothing to rewrite.
        return [
            'role' => FluentRule::string()->in(['admin', 'user']),
            'count' => FluentRule::numeric()->min(0)->max(100),
            'pattern' => FluentRule::string()->regex('/^\d+$/'),
        ];
    }
}

?>
