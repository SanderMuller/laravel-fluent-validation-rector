<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPresenceOrder\Fixture\RunsFirst;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// laravel-fluent-validation 1.45.1+ compiles a closure or bool conditional
// before the Password object in password() too, so the order matches string()
// and the chain is promoted.

$rules = [
    'password' => FluentRule::string()->excludeIf(fn (): bool => true)->rule(Password::default()),
    'secret' => FluentRule::string()->rule(Password::default())->requiredIf(true),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPresenceOrder\Fixture\RunsFirst;

use SanderMuller\FluentValidation\FluentRule;

// laravel-fluent-validation 1.45.1+ compiles a closure or bool conditional
// before the Password object in password() too, so the order matches string()
// and the chain is promoted.

$rules = [
    'password' => FluentRule::password()->excludeIf(fn (): bool => true),
    'secret' => FluentRule::password()->requiredIf(true),
];

?>
