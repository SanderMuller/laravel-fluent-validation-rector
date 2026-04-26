<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. `string()->rule('email')` and `string()->email()` both
 * lower to Laravel's `email` rule with no implicit constraint delta.
 */
return [
    'rules_before' => [
        'address' => FluentRule::string()->required()->rule('email'),
    ],
    'rules_after' => [
        'address' => FluentRule::string()->required()->email(),
    ],
    'payloads' => [
        'missing' => [],
        'valid email' => ['address' => 'user@example.test'],
        'plain string' => ['address' => 'not-an-email'],
        'integer' => ['address' => 42],
        'empty string' => ['address' => ''],
    ],
];
