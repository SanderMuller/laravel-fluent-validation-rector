<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

// Codex review catch (2026-04-24): StringRule::min(int, ?string) adds a
// string-length rule. PasswordRule::min(int) mutates the password builder's
// min chars. Different semantics AND different signatures — promoting
// `string()->min(20)` to `password()->min(20)` would silently shift intent.

$rules = [
    'password' => FluentRule::string()->required()->min(20)->rule(Password::default()),
];

?>
