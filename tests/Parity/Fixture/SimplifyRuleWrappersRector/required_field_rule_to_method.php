<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. `field()->rule('required')` and `field()->required()`
 * lower to identical underlying rules.
 */
return [
    'rules_before' => [
        'name' => FluentRule::field()->rule('required'),
    ],
    'rules_after' => [
        'name' => FluentRule::field()->required(),
    ],
    'payloads' => [
        'missing' => [],
        'present string' => ['name' => 'Alice'],
        'present empty string' => ['name' => ''],
        'present null' => ['name' => null],
        'present integer' => ['name' => 0],
    ],
];
