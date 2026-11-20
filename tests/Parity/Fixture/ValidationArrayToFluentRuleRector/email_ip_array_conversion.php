<?php declare(strict_types=1);

use Composer\InstalledVersions;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\Parity\DivergenceCategory;

/**
 * Array conversion to the typed `email()` and `ip()` factories. Same two
 * accepted differences as the string conversion: `email()` reports the
 * format check before `max` (1.42.0+), and the implicit `string` rule adds a message
 * for a non-string value.
 */
return [
    'rules_before' => [
        'address' => ['required', 'max:255', 'email'],
        'host' => ['required', 'ip'],
    ],
    'rules_after' => [
        'address' => FluentRule::email(defaults: false)->required()->max(255),
        'host' => FluentRule::ip()->required(),
    ],
    'payloads' => [
        'missing' => [],
        'valid' => ['address' => 'user@example.test', 'host' => '192.0.2.1'],
        'invalid strings' => ['address' => 'not-an-email', 'host' => 'not-an-ip'],
        'too long and not an email' => ['address' => str_repeat('a', 300), 'host' => '192.0.2.1'],
        'arrays' => ['address' => ['user@example.test'], 'host' => ['192.0.2.1']],
    ],
    'allowed_divergences' => [
        // laravel-fluent-validation 1.42.0 moved the format check before max.
        ...(version_compare(InstalledVersions::getVersion('sandermuller/laravel-fluent-validation') ?? '0', '1.42.0', '>=')
            ? ['too long and not an email' => [
                'category' => DivergenceCategory::OrderDependentPipeline,
                'rationale' => 'email() reports the format check before max, the order of required|string|email|max:255',
            ]]
            : []),
        'arrays' => [
            'category' => DivergenceCategory::AddedTypeMessage,
            'rationale' => 'the typed factories add string; email and ip already reject an array',
        ],
    ],
];
