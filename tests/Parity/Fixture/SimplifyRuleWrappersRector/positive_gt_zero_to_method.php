<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Backfill: 1.19.0 sign-helper widening. SimplifyRuleWrappersRector lowers
 * `numeric()->rule('gt:0')` (literal-zero arg) to `numeric()->positive()`.
 * Both apply Laravel's `gt:0` rule under the hood — full match expected.
 *
 * The rector gates this widening on the literal-zero check to avoid
 * incorrectly promoting `gt:5` etc.; the parity fixture pins the behavior
 * for the supported (literal-zero) shape.
 */
return [
    'rules_before' => [
        'amount' => FluentRule::numeric()->required()->rule('gt:0'),
    ],
    'rules_after' => [
        'amount' => FluentRule::numeric()->required()->positive(),
    ],
    'payloads' => [
        'missing' => [],
        'positive integer' => ['amount' => 42],
        'positive float' => ['amount' => 0.01],
        'zero' => ['amount' => 0],
        'negative' => ['amount' => -5],
        'plain string' => ['amount' => 'not-a-number'],
    ],
];
