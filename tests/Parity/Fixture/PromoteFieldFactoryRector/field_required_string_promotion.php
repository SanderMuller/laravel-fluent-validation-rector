<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. Promotes `field()->required()->string()` to
 * `string()->required()`. Same underlying rules, same Laravel runtime
 * application.
 */
return [
    'rules_before' => [
        'name' => FluentRule::field()->required()->rule('string'),
    ],
    'rules_after' => [
        'name' => FluentRule::string()->required(),
    ],
    'payloads' => [
        'missing' => [],
        'valid string' => ['name' => 'Alice'],
        'integer' => ['name' => 42],
        'array' => ['name' => ['nested']],
        'null' => ['name' => null],
    ],
];
