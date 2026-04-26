<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Backfill: 0.10.1 `requiredArrayKeys` widening. Pre-rector
 * `array()->rule('required_array_keys:foo,bar')` lowers to
 * `array()->requiredArrayKeys('foo', 'bar')`. ArrayRule-only rewrite,
 * gated by per-class method-availability allowlist.
 */
return [
    'rules_before' => [
        'config' => FluentRule::array()->required()->rule('required_array_keys:host,port'),
    ],
    'rules_after' => [
        'config' => FluentRule::array()->required()->requiredArrayKeys('host', 'port'),
    ],
    'payloads' => [
        'missing' => [],
        'all keys present' => ['config' => ['host' => 'localhost', 'port' => 5432]],
        'missing host' => ['config' => ['port' => 5432]],
        'missing both' => ['config' => []],
        'extra keys allowed' => ['config' => ['host' => 'localhost', 'port' => 5432, 'extra' => 1]],
        'non-array' => ['config' => 'string-not-array'],
    ],
];
