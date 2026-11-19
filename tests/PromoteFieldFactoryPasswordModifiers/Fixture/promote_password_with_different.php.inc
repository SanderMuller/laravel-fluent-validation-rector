<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPasswordModifiers\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// different() is a rule string on both classes, so its position relative
// to the ->rule(Password::default()) hop does not change the compiled order.

$rules = [
    'new_password' => FluentRule::string()->required()->rule(Password::default())->different('current_password'),
    'other_password' => FluentRule::string()->different('current_password')->rule(Password::default()),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPasswordModifiers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// different() is a rule string on both classes, so its position relative
// to the ->rule(Password::default()) hop does not change the compiled order.

$rules = [
    'new_password' => FluentRule::password()->required()->different('current_password'),
    'other_password' => FluentRule::password()->different('current_password'),
];

?>
