<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

class ValidatorHelperFunction
{
    public function handle(array $input): void
    {
        \validator(
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

class ValidatorHelperFunction
{
    public function handle(array $input): void
    {
        \validator(
            $input,
            [
                'email' => FluentRule::email()->required()->max(255),
                'name' => FluentRule::string()->required(),
            ],
        );
    }
}

?>
