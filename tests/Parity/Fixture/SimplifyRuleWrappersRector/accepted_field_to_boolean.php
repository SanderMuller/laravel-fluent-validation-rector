<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\Parity\DivergenceCategory;

/**
 * Parity fixture: pre-rector `'required|accepted'` ↔ post-rector
 * `FluentRule::boolean()->required()->accepted()`.
 *
 * The `accepted` rule (Laravel) accepts the values `'yes' | 'on' | 1 | '1' | true | 'true'`.
 * The promoted `boolean()->accepted()` chain pre-validates `boolean` (which itself
 * accepts a different value set: `true | false | 1 | 0 | '1' | '0'`), so 'yes' / 'on'
 * are rejected by the post-rector form even though the pre-rector form accepts them.
 *
 * That's the implicit-type-constraint divergence the parity harness exists to surface.
 */
return [
    'rules_before' => [
        'tos' => 'required|accepted',
    ],
    'rules_after' => [
        'tos' => FluentRule::boolean()->required()->accepted(),
    ],
    'payloads' => [
        'tos missing' => [],
        'tos boolean true' => ['tos' => true],
        'tos string yes' => ['tos' => 'yes'],
        'tos string on' => ['tos' => 'on'],
        'tos integer 1' => ['tos' => 1],
        'tos integer 0' => ['tos' => 0],
    ],
    'allowed_divergences' => [
        'tos string yes' => [
            'category' => DivergenceCategory::ImplicitTypeConstraint,
            'rationale' => "boolean()'s accepted-value set excludes 'yes' / 'on' / 'true' string forms",
        ],
        'tos string on' => [
            'category' => DivergenceCategory::ImplicitTypeConstraint,
            'rationale' => "boolean()'s accepted-value set excludes 'yes' / 'on' / 'true' string forms",
        ],
    ],
];
