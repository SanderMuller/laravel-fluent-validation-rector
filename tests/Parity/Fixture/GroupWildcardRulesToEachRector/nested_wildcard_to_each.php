<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\Parity\DivergenceCategory;

/**
 * Nested wildcard collapse. Two-level wildcard sibling keys
 * (`matrix` / `matrix.*` / `matrix.*.*`) fold into nested `each()` calls.
 *
 * The post-rector form uses `FluentRule::integer()`, which implicitly emits
 * an additional `numeric` message alongside `integer` when both fail (the
 * fluent-validation IntegerRule chains `numeric` ahead of `integer`). The
 * pre-rector `'integer|min:1'` string form skips the numeric step. Payloads
 * that hit non-numeric inner values surface the divergence; documented as
 * MessageKeyDrift.
 */
return [
    'rules_before' => [
        'matrix' => 'array',
        'matrix.*' => 'array',
        'matrix.*.*' => 'integer|min:1',
    ],
    'rules_after' => [
        'matrix' => FluentRule::array()->each(
            FluentRule::array()->each(FluentRule::integer()->min(1)),
        ),
    ],
    'payloads' => [
        'missing' => [],
        'empty outer' => ['matrix' => []],
        'all valid' => ['matrix' => [[1, 2], [3, 4]]],
        'inner zero' => ['matrix' => [[1, 0]]],
        'inner string' => ['matrix' => [[1, 'two']]],
    ],
    'allowed_divergences' => [
        'inner string' => [
            'category' => DivergenceCategory::MessageKeyDrift,
            'rationale' => 'FluentRule::integer() emits implicit numeric+integer messages; pre-rector "integer" string emits only the integer message',
        ],
    ],
];
