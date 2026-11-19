<?php declare(strict_types=1);

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Internal\PresenceConditionalOrder;

/**
 * Array conversion of password and email rules that hold a presence
 * conditional object. Pair copied from tests/ValidationArrayPresenceOrder/
 * Fixture/RunsFirst/promote_password_email_with_presence_conditional_object.php.inc,
 * with a failing prohibitedIf(true) added. The converter converts these
 * arrays only when the installed version compiles the conditional first.
 */
if (! PresenceConditionalOrder::runsFirst('password') || ! PresenceConditionalOrder::runsFirst('email')) {
    return ['skip' => 'password() or email() compiles presence conditionals last on this version; the converter keeps the array.'];
}

return [
    'rules_before' => [
        'password' => [Rule::excludeIf(fn (): bool => true), 'required', Password::default()],
        'pin' => ['required', Password::min(4), Rule::requiredIf(true)],
        'email' => [new ExcludeIf(fn (): bool => true), Email::default()],
        'backup_email' => [Rule::prohibitedIf(false), Email::default()],
        'old_email' => [Rule::prohibitedIf(true), Email::default()],
    ],
    'rules_after' => [
        'password' => FluentRule::password()->excludeIf(fn (): bool => true)->required(),
        'pin' => FluentRule::password(4)->required()->requiredIf(true),
        'email' => FluentRule::email()->rule(new ExcludeIf(fn (): bool => true)),
        'backup_email' => FluentRule::email()->prohibitedIf(false),
        'old_email' => FluentRule::email()->prohibitedIf(true),
    ],
    'payloads' => [
        'missing' => [],
        'empty' => ['password' => '', 'pin' => ''],
        'invalid' => ['password' => 'abc', 'pin' => 'ab', 'email' => 'nope', 'backup_email' => 'nope', 'old_email' => 'nope'],
        'pin not a string' => ['pin' => 12345],
        'valid' => ['pin' => 'abcd', 'backup_email' => 'b@example.com'],
    ],
];
