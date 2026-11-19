<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPasswordModifiers\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// PasswordRule::notIn() compiles before the Password object, which is where
// string()->notIn() lands when it comes before the ->rule(Password::default()) hop.

$rules = [
    'password' => FluentRule::string()->required()->notIn(['password', 'secret'])->rule(Password::default()),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPasswordModifiers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// PasswordRule::notIn() compiles before the Password object, which is where
// string()->notIn() lands when it comes before the ->rule(Password::default()) hop.

$rules = [
    'password' => FluentRule::password()->required()->notIn(['password', 'secret']),
];

?>
