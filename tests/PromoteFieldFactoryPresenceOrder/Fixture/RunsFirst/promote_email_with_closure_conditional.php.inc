<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPresenceOrder\Fixture\RunsFirst;

use Illuminate\Validation\Rules\Email;
use SanderMuller\FluentValidation\FluentRule;

// laravel-fluent-validation 1.45.1+ compiles a closure or bool conditional
// before the email check in email() too, so the order matches string() and
// the chain is promoted.

$rules = [
    'email' => FluentRule::string()->excludeIf(fn (): bool => true)->rule(Email::default()),
    'backup_email' => FluentRule::string()->rule(Email::default())->prohibitedIf(false),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPresenceOrder\Fixture\RunsFirst;

use SanderMuller\FluentValidation\FluentRule;

// laravel-fluent-validation 1.45.1+ compiles a closure or bool conditional
// before the email check in email() too, so the order matches string() and
// the chain is promoted.

$rules = [
    'email' => FluentRule::email()->excludeIf(fn (): bool => true),
    'backup_email' => FluentRule::email()->prohibitedIf(false),
];

?>
