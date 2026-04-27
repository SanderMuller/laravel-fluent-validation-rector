<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity for the 0.19.0 wildcard-prefix-concat fold.
 *
 * Pre-rector shape: `'*.<field>' => …` for each sibling — flat top-
 * level keys with literal `*.` prefix and a constant-suffix field
 * name. Post-rector shape: `'*' => array()->children([<field> => …])`.
 *
 * The parity harness asserts the two shapes produce equivalent
 * Laravel runtime errors on the same payload, which is what the
 * spec §3 ClassConstFetch-preservation invariant demands at the
 * AST level holds at the runtime level too.
 *
 * The harness uses literal-string children keys (parity fixtures
 * are pure data — no const-host classes available to dereference).
 * The fold's actual emit preserves `ClassConstFetch` keys verbatim;
 * Laravel resolves them at runtime to the same strings used here.
 */
return [
    'rules_before' => [
        '*.title' => FluentRule::string()->required()->max(255),
        '*.sort_order' => FluentRule::integer()->required()->min(0),
    ],
    'rules_after' => [
        '*' => FluentRule::array()->children([
            'title' => FluentRule::string()->required()->max(255),
            'sort_order' => FluentRule::integer()->required()->min(0),
        ]),
    ],
    'payloads' => [
        'missing' => [],
        'empty array' => [],
        'single item all valid' => [['title' => 'My title', 'sort_order' => 1]],
        'single item title missing' => [['sort_order' => 1]],
        'single item sort_order missing' => [['title' => 'My title']],
        'multi item all valid' => [
            ['title' => 'First', 'sort_order' => 1],
            ['title' => 'Second', 'sort_order' => 2],
        ],
        'multi item one invalid' => [
            ['title' => 'First', 'sort_order' => 1],
            ['title' => 'Second', 'sort_order' => -5],
        ],
        'title too long' => [['title' => str_repeat('x', 300), 'sort_order' => 1]],
        'sort_order non-integer' => [['title' => 'X', 'sort_order' => 'not-a-number']],
        'sort_order negative' => [['title' => 'X', 'sort_order' => -1]],
    ],
];
