<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipPlainFieldWithNoRuleCalls
{
    public function rules(): array
    {
        return [
            // No ->rule() hops → nothing to constrain the promotion. Leave alone.
            'raw' => FluentRule::field()->required()->nullable(),
        ];
    }
}

?>
