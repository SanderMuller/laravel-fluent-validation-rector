<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. Promotes `field()->required()->rule('accepted')` to
 * `accepted()->required()`. AcceptedRule's constructor seeds `'accepted'`,
 * so dropping the `->rule()` hop preserves the exact validation output —
 * the rule must accept `"yes"`/`"on"`/`"true"`/`1`/`"1"`/`true` and reject
 * everything else, on both shapes.
 */
return [
    'rules_before' => [
        'agreed' => FluentRule::field()->required()->rule('accepted'),
    ],
    'rules_after' => [
        'agreed' => FluentRule::accepted()->required(),
    ],
    'payloads' => [
        'missing' => [],
        'valid yes' => ['agreed' => 'yes'],
        'valid on' => ['agreed' => 'on'],
        'valid true string' => ['agreed' => 'true'],
        'valid one' => ['agreed' => 1],
        'valid one string' => ['agreed' => '1'],
        'valid true bool' => ['agreed' => true],
        'rejects zero' => ['agreed' => 0],
        'rejects no' => ['agreed' => 'no'],
        'rejects empty' => ['agreed' => ''],
        'null' => ['agreed' => null],
    ],
];
