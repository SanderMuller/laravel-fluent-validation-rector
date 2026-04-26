<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. Pre-rector wildcard sibling keys (`items` + `items.*`)
 * fold into a single `array()->each(...)` chain post-rector. Laravel applies
 * the inner rule to each wildcard match in both shapes.
 */
return [
    'rules_before' => [
        'items' => 'array',
        'items.*' => 'string|max:10',
    ],
    'rules_after' => [
        'items' => FluentRule::array()->each(FluentRule::string()->max(10)),
    ],
    'payloads' => [
        'missing' => [],
        'empty array' => ['items' => []],
        'all valid' => ['items' => ['abc', 'def']],
        'one too long' => ['items' => ['abc', 'this-is-too-long']],
        'integer element' => ['items' => ['abc', 42]],
        'non-array' => ['items' => 'not-an-array'],
    ],
];
