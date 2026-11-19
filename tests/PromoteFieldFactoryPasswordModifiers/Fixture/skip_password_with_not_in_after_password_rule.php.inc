<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPasswordModifiers\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// string() compiles this notIn() after the Password object. password() would
// compile it before, which changes the first error under bail(). The chain
// stays on string().

$rules = [
    'password' => FluentRule::string()->required()->rule(Password::default())->notIn(['password', 'secret']),
];

?>
