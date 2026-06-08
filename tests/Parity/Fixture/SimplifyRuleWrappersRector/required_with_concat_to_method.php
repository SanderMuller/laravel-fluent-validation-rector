<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Backfill: Concat-payload comma-separated rules on the pure-field family.
 * Pre-rector `string()->rule('required_with:' . $field)` lowers to
 * `string()->requiredWith($field)`. Passing the whole post-colon expression
 * as a single field arg reproduces the exact rule string because the fluent
 * method rebuilds `'required_with:' . implode(',', $fields)`.
 */
$end = 'end';

return [
    'rules_before' => [
        'start' => FluentRule::string()->nullable()->rule('required_with:' . $end),
        'end' => FluentRule::string()->nullable(),
    ],
    'rules_after' => [
        'start' => FluentRule::string()->nullable()->requiredWith($end),
        'end' => FluentRule::string()->nullable(),
    ],
    'payloads' => [
        'both present' => ['start' => '2026-01-01', 'end' => '2026-01-02'],
        'end present, start missing' => ['end' => '2026-01-02'],
        'neither present' => [],
        'start present, end missing' => ['start' => '2026-01-01'],
    ],
];
