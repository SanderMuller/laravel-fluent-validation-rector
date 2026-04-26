<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Validation\Rule;

class ValidatorHelperFunctionArray
{
    public function handle(array $input): void
    {
        \validator(
            $input,
            [
                'email' => ['required', 'email', Rule::unique('users', 'email')],
            ],
        );
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Validation\Rule;

class ValidatorHelperFunctionArray
{
    public function handle(array $input): void
    {
        \validator(
            $input,
            [
                'email' => FluentRule::email()->required()->unique('users', 'email'),
            ],
        );
    }
}

?>
