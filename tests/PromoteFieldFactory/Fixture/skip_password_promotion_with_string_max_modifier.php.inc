<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// Same divergence as `->min()`: StringRule::max and PasswordRule::max have
// different signatures/semantics. Bail.

$rules = [
    'password' => FluentRule::string()->required()->max(255)->rule(Password::default()),
];

?>
