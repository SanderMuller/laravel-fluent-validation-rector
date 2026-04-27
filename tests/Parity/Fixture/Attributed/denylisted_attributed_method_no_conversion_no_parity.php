<?php declare(strict_types=1);

/**
 * Parity contract smoke test: when the denylist blocks conversion
 * (e.g. `#[FluentRules] casts()`), no transformation runs. The
 * rule sets the harness sees are identical (rules_before ===
 * rules_after) and Laravel produces the same error bag on both
 * sides. The harness must classify this as MATCH — proves the
 * tool gracefully handles "no transformation to validate" without
 * false-positive divergences.
 */
return [
    'rules_before' => [
        'active' => 'boolean',
    ],
    'rules_after' => [
        'active' => 'boolean',
    ],
    'payloads' => [
        'missing' => [],
        'true' => ['active' => true],
        'string' => ['active' => 'yes'],
        'integer one' => ['active' => 1],
    ],
];
