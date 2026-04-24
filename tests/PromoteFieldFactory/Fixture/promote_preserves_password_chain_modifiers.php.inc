<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'password' => FluentRule::string()
        ->required()
        ->requiredWith('token')
        ->bail()
        ->label('Password')
        ->rule(Password::default()),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'password' => FluentRule::password()
        ->required()
        ->requiredWith('token')
        ->bail()
        ->label('Password'),
];

?>
