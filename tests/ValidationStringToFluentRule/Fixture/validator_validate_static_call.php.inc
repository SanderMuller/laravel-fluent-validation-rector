<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Support\Facades\Validator;

class ValidatorValidateStaticCall
{
    public function handle(array $input): void
    {
        Validator::validate(
            $input,
            [
                'email' => 'required|email|max:255',
                'name' => 'required|string',
            ],
        );
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Support\Facades\Validator;

class ValidatorValidateStaticCall
{
    public function handle(array $input): void
    {
        Validator::validate(
            $input,
            [
                'email' => FluentRule::email()->required()->max(255),
                'name' => FluentRule::string()->required(),
            ],
        );
    }
}

?>
