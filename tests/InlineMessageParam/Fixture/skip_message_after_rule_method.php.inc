<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipMessageAfterRuleMethod
{
    public function rules(): array
    {
        // ->message() after a rule method binds to the rule, not the
        // factory. Collapsing to factory-level message: would retarget.
        return [
            'email' => FluentRule::email()->required()->message('Email required.'),
            'name' => FluentRule::string()->min(3)->message('Too short.'),
        ];
    }
}

?>
