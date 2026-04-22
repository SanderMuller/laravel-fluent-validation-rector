<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipFactoryPassword
{
    public function rules(): array
    {
        // password factory has no message: param — L11/L12-divergent
        // password sub-key lookup means the chain stays untouched.
        return [
            'password' => FluentRule::password()->message('Bad password.'),
        ];
    }
}

?>
