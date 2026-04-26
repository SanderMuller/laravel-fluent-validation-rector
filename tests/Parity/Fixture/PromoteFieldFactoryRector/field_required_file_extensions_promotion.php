<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. Promotes the (typed-builder-equivalent) chain
 * `file()->required()->extensions('pdf')` from a `field()` escape-hatch
 * pre-shape to its typed-factory counterpart.
 *
 * Note: `field()->required()->file()` would fatal at runtime per the
 * fluent-validation API (`field()` doesn't expose typed methods directly).
 * The pre-rector form here uses `->rule('file')->rule('extensions:pdf')`
 * which is the runtime-valid escape-hatch shape. The harness diffs
 * runtime equivalence between the two rule arrays, not a literal AST diff.
 */
return [
    'rules_before' => [
        'document' => FluentRule::field()->required()->rule('file')->rule('extensions:pdf'),
    ],
    'rules_after' => [
        'document' => FluentRule::file()->required()->extensions('pdf'),
    ],
    'payloads' => [
        'missing' => [],
        'plain string' => ['document' => '/path/to/file.pdf'],
        'integer' => ['document' => 1],
        'null' => ['document' => null],
    ],
];
