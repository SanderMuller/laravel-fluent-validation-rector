<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Backfill: string-form comma-separated rules. Pre-rector
 * `string()->rule('required_with:end')` lowers to
 * `string()->requiredWith('end')`. The fluent variadic rebuilds
 * `'required_with:' . implode(',', $fields)`, so the two validate identically.
 */
return [
    'rules_before' => [
        'start' => FluentRule::string()->nullable()->rule('required_with:end'),
        'end' => FluentRule::string()->nullable(),
    ],
    'rules_after' => [
        'start' => FluentRule::string()->nullable()->requiredWith('end'),
        'end' => FluentRule::string()->nullable(),
    ],
    'payloads' => [
        'both present' => ['start' => '2026-01-01', 'end' => '2026-01-02'],
        'end present, start missing' => ['end' => '2026-01-02'],
        'neither present' => [],
        'start present, end missing' => ['start' => '2026-01-01'],
    ],
];
