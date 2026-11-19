<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// A closure or bool conditional adds a rule object. string() compiles it
// before the Password object, but password() compiles it after, so an
// exclude would no longer stop the Password check. The chain stays on string().

$rules = [
    'password' => FluentRule::string()->excludeIf(fn (): bool => true)->rule(Password::default()),
    'secret' => FluentRule::string()->rule(Password::default())->requiredIf(true),
];

?>
