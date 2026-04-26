<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. The 1.19.0 widening promoted `string()->rule('ipv4')` to
 * the top-level `FluentRule::ipv4()` factory; both produce the same Laravel
 * `ipv4` rule application.
 */
return [
    'rules_before' => [
        'host' => FluentRule::string()->required()->rule('ipv4'),
    ],
    'rules_after' => [
        'host' => FluentRule::ipv4()->required(),
    ],
    'payloads' => [
        'missing' => [],
        'valid ipv4' => ['host' => '192.168.1.1'],
        'invalid ipv4' => ['host' => '999.999.999.999'],
        'ipv6' => ['host' => '::1'],
        'plain string' => ['host' => 'example.test'],
    ],
];
