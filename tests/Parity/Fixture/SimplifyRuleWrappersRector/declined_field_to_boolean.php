<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\Parity\DivergenceCategory;

/**
 * Symmetric counterpart of the `accepted` fixture. `declined`'s accepted-value
 * set (`'no' | 'off' | '0' | 0 | false | 'false'`) is wider than `boolean()`'s
 * (`true | false | 1 | 0 | '1' | '0'`), so `'no' / 'off'` strings diverge.
 */
return [
    'rules_before' => [
        'opt_out' => 'required|declined',
    ],
    'rules_after' => [
        'opt_out' => FluentRule::boolean()->required()->declined(),
    ],
    'payloads' => [
        'missing' => [],
        'boolean false' => ['opt_out' => false],
        'string no' => ['opt_out' => 'no'],
        'string off' => ['opt_out' => 'off'],
        'integer 0' => ['opt_out' => 0],
        'integer 1' => ['opt_out' => 1],
    ],
    'allowed_divergences' => [
        'string no' => [
            'category' => DivergenceCategory::ImplicitTypeConstraint,
            'rationale' => "boolean()'s accepted-value set excludes 'no' / 'off' / 'false' string forms",
        ],
        'string off' => [
            'category' => DivergenceCategory::ImplicitTypeConstraint,
            'rationale' => "boolean()'s accepted-value set excludes 'no' / 'off' / 'false' string forms",
        ],
    ],
];
