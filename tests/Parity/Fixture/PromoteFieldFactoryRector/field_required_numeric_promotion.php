<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. Promotes `field()->required()->numeric()` to
 * `numeric()->required()`.
 */
return [
    'rules_before' => [
        'amount' => FluentRule::field()->required()->rule('numeric'),
    ],
    'rules_after' => [
        'amount' => FluentRule::numeric()->required(),
    ],
    'payloads' => [
        'missing' => [],
        'valid integer' => ['amount' => 42],
        'valid float' => ['amount' => 3.14],
        'numeric string' => ['amount' => '99.99'],
        'plain string' => ['amount' => 'not-a-number'],
        'null' => ['amount' => null],
    ],
];
