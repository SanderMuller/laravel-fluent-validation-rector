<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// Safety Gate #1: source `FluentRule::string('Password')` has a label arg.
// Promoting to `FluentRule::password('Password')` would rebind 'Password'
// from `?string $label` to `?int $min` — TypeError at runtime. Zero-arg
// source only in v1.

$rules = [
    'password' => FluentRule::string('Password')->required()->rule(Password::default()),
];

?>
