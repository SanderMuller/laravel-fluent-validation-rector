<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ValidatorValidateArrayStaticCall
{
    public function handle(array $input): void
    {
        Validator::validate(
            $input,
            [
                'email' => ['required', 'email', Rule::unique('users', 'email')],
                'role' => ['required', Rule::in(['admin', 'editor'])],
            ],
        );
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ValidatorValidateArrayStaticCall
{
    public function handle(array $input): void
    {
        Validator::validate(
            $input,
            [
                'email' => FluentRule::email()->required()->unique('users', 'email'),
                'role' => FluentRule::field()->required()->in(['admin', 'editor']),
            ],
        );
    }
}

?>
