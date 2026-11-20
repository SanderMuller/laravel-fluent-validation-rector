<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\Parity\DivergenceCategory;

/**
 * String conversion to the typed `email()`, `url()` and `uuid()` factories.
 * Both accepted differences show here:
 *
 * - `email()` runs its format check right after `string`, so a value that
 *   fails both `max` and `email` reports `email` first.
 * - The typed factories add an implicit `string` rule. Laravel's `email`,
 *   `url` and `uuid` rules already reject a non-string, so pass or fail
 *   does not change, but a non-string gets one more message.
 */
return [
    'rules_before' => [
        'address' => 'required|max:255|email',
        'website' => 'required|url',
        'token' => 'nullable|uuid',
    ],
    'rules_after' => [
        'address' => FluentRule::email(defaults: false)->required()->max(255),
        'website' => FluentRule::url()->required(),
        'token' => FluentRule::uuid()->nullable(),
    ],
    'payloads' => [
        'missing' => [],
        'valid' => ['address' => 'user@example.test', 'website' => 'https://example.test', 'token' => '0b6f1a8e-6c2e-4b8a-9d3f-1f2e3d4c5b6a'],
        'invalid strings' => ['address' => 'not-an-email', 'website' => 'not a url', 'token' => 'nope'],
        'too long and not an email' => ['address' => str_repeat('a', 300), 'website' => 'https://example.test'],
        'integers' => ['address' => 42, 'website' => 42, 'token' => 42],
    ],
    'allowed_divergences' => [
        'too long and not an email' => [
            'category' => DivergenceCategory::OrderDependentPipeline,
            'rationale' => 'email() reports the format check before max, the order of required|string|email|max:255',
        ],
        'integers' => [
            'category' => DivergenceCategory::AddedTypeMessage,
            'rationale' => 'the typed factories add string; email, url and uuid already reject an integer',
        ],
    ],
];
