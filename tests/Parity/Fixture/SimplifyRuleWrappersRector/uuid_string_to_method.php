<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. `string()->rule('uuid')` and `string()->uuid()` lower to
 * identical Laravel `uuid` rule application.
 */
return [
    'rules_before' => [
        'id' => FluentRule::string()->required()->rule('uuid'),
    ],
    'rules_after' => [
        'id' => FluentRule::string()->required()->uuid(),
    ],
    'payloads' => [
        'missing' => [],
        'valid v4' => ['id' => '550e8400-e29b-41d4-a716-446655440000'],
        'invalid' => ['id' => 'not-a-uuid'],
        'integer' => ['id' => 12345],
        'empty string' => ['id' => ''],
    ],
];
