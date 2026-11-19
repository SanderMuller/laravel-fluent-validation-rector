<?php declare(strict_types=1);

use Illuminate\Validation\Rules\Email;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Internal\PresenceConditionalOrder;

/**
 * Promotion of a closure or bool presence conditional to `email()`.
 * Pair copied from tests/PromoteFieldFactoryPresenceOrder/Fixture/RunsFirst/
 * promote_email_with_closure_conditional.php.inc, with prohibitedIf(true)
 * added so the conditional fails. The rector promotes only when the
 * installed version compiles the conditional before the email check.
 */
if (! PresenceConditionalOrder::runsFirst('email')) {
    return ['skip' => 'email() compiles presence conditionals after the email check on this version; the rector does not promote.'];
}

return [
    'rules_before' => [
        'email' => FluentRule::string()->excludeIf(fn (): bool => true)->rule(Email::default()),
        'backup_email' => FluentRule::string()->rule(Email::default())->prohibitedIf(false),
        'old_email' => FluentRule::string()->rule(Email::default())->prohibitedIf(true),
    ],
    'rules_after' => [
        'email' => FluentRule::email()->excludeIf(fn (): bool => true),
        'backup_email' => FluentRule::email()->prohibitedIf(false),
        'old_email' => FluentRule::email()->prohibitedIf(true),
    ],
    'payloads' => [
        'missing' => [],
        'invalid' => ['email' => 'not-an-email', 'backup_email' => 'not-an-email', 'old_email' => 'not-an-email'],
        'valid' => ['email' => 'a@example.com', 'backup_email' => 'b@example.com'],
        'prohibited and valid' => ['old_email' => 'c@example.com'],
    ],
];
