<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPresenceOrder\Fixture\RunsLast;

use Illuminate\Validation\Rules\Email;
use SanderMuller\FluentValidation\FluentRule;

// A closure or bool conditional adds a rule object. string() compiles it
// before the Email object. email() up to laravel-fluent-validation 1.45.0
// compiles it after, so an exclude would no longer stop the email check.
// The chain stays on string().

$rules = [
    'email' => FluentRule::string()->excludeIf(fn (): bool => true)->rule(Email::default()),
    'backup_email' => FluentRule::string()->rule(Email::default())->prohibitedIf(false),
];

?>
