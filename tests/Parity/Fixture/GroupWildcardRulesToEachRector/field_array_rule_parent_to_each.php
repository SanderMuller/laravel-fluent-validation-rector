<?php declare(strict_types=1);

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. A `field()->nullable()->rule(Rule::array())` parent is
 * equivalent to an `array()->nullable()` parent — the array factory seeds the
 * same `array` rule — so it folds into `array()->nullable()->each(...)`.
 * Laravel applies the inner rule to each wildcard match in both shapes, and a
 * missing/null parent passes either way (nullable).
 */
return [
    'rules_before' => [
        'items' => FluentRule::field()->nullable()->rule(Rule::array()),
        'items.*' => FluentRule::string()->nullable()->max(255),
    ],
    'rules_after' => [
        'items' => FluentRule::array()->nullable()->each(FluentRule::string()->nullable()->max(255)),
    ],
    'payloads' => [
        'missing' => [],
        'null parent' => ['items' => null],
        'empty array' => ['items' => []],
        'all valid' => ['items' => ['abc', 'def']],
        'one too long' => ['items' => ['abc', str_repeat('x', 256)]],
        'integer element' => ['items' => ['abc', 42]],
        'non-array' => ['items' => 'not-an-array'],
    ],
];
