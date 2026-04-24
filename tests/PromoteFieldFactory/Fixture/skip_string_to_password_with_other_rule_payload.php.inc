<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// Safety Gate #3: chain has ->rule() payloads besides the Password one.
// The extra `->rule('regex:...')` would bind to FluentRule::password() which
// already bakes in its own ruleset — behavior unclear. Bail.

$rules = [
    'password' => FluentRule::string()->required()->rule('regex:/^[a-z]+$/')->rule(Password::default()),
];

?>
