<?php declare(strict_types=1);

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\PasswordRule;

/**
 * Promotion of notIn() before the Password hop and different() anywhere to
 * `password()`. Pairs copied from tests/PromoteFieldFactoryPasswordModifiers/
 * Fixture/promote_password_with_not_in_before_password_rule.php.inc and
 * promote_password_with_different.php.inc. PasswordRule has both methods
 * from laravel-fluent-validation 1.45 on; the rector skips the promotion
 * before that.
 */
if (! method_exists(PasswordRule::class, 'notIn') || ! method_exists(PasswordRule::class, 'different')) {
    return ['skip' => 'PasswordRule has no notIn() or different() on this version; the rector does not promote.'];
}

return [
    'rules_before' => [
        'password' => FluentRule::string()->required()->notIn(['password', 'secret'])->rule(Password::default()),
        'new_password' => FluentRule::string()->required()->rule(Password::default())->different('current_password'),
        'other_password' => FluentRule::string()->different('current_password')->rule(Password::default()),
    ],
    'rules_after' => [
        'password' => FluentRule::password()->required()->notIn(['password', 'secret']),
        'new_password' => FluentRule::password()->required()->different('current_password'),
        'other_password' => FluentRule::password()->different('current_password'),
    ],
    'payloads' => [
        'missing' => [],
        'listed and long enough' => ['password' => 'password'],
        'listed and too short' => ['password' => 'secret'],
        'same as current and too short' => ['current_password' => 'abc', 'new_password' => 'abc', 'other_password' => 'abc'],
        'same as current' => ['current_password' => 'long-enough-value', 'new_password' => 'long-enough-value', 'other_password' => 'long-enough-value'],
        'not a string' => ['password' => ['secret'], 'new_password' => 12345],
        'valid' => ['password' => 'long-enough-value', 'current_password' => 'abc', 'new_password' => 'long-enough-value'],
    ],
];
