<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPresenceOrder\Fixture\RunsLast;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// A closure or bool conditional adds a rule object. string() compiles it
// before the Password object. password() up to laravel-fluent-validation
// 1.45.0 compiles it after, so an exclude would no longer stop the Password
// check. The chain stays on string().

$rules = [
    'password' => FluentRule::string()->excludeIf(fn (): bool => true)->rule(Password::default()),
    'secret' => FluentRule::string()->rule(Password::default())->requiredIf(true),
];

?>
