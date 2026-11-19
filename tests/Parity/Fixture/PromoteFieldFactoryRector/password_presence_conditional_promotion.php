<?php declare(strict_types=1);

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Internal\PresenceConditionalOrder;

/**
 * Promotion of a closure or bool presence conditional to `password()`.
 * Pair copied from tests/PromoteFieldFactoryPresenceOrder/Fixture/RunsFirst/
 * promote_password_with_closure_conditional.php.inc. The rector promotes
 * only when the installed version compiles the conditional before the
 * Password object, so the pair runs under the same probe.
 */
if (! PresenceConditionalOrder::runsFirst('password')) {
    return ['skip' => 'password() compiles presence conditionals after the Password object on this version; the rector does not promote.'];
}

return [
    'rules_before' => [
        'password' => FluentRule::string()->excludeIf(fn (): bool => true)->rule(Password::default()),
        'secret' => FluentRule::string()->rule(Password::default())->requiredIf(true),
    ],
    'rules_after' => [
        'password' => FluentRule::password()->excludeIf(fn (): bool => true),
        'secret' => FluentRule::password()->requiredIf(true),
    ],
    'payloads' => [
        'missing' => [],
        'empty' => ['password' => '', 'secret' => ''],
        'too short' => ['password' => 'abc', 'secret' => 'abc'],
        'not a string' => ['password' => ['abc'], 'secret' => 12345],
        'valid' => ['password' => 'abc', 'secret' => 'long-enough-value'],
    ],
];
