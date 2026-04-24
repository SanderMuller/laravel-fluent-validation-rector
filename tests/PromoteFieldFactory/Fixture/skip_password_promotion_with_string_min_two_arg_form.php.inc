<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// Codex review catch (2026-04-24): the 2-arg form `StringRule::min(int, ?string)`
// would TypeError at runtime if promoted to `PasswordRule::min(int)`. Bail.

$rules = [
    'password' => FluentRule::string()->required()->min(20, 'Too short!')->rule(Password::default()),
];

?>
