<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Parity coverage for the 1.1 / 0.16 unlock: the abstract-with-rules
 * guard now lifts when `rules()` carries `#[FluentRules]`. The
 * converter's pure-refactor host rectors
 * (`ValidationStringToFluentRuleRector` /
 * `ValidationArrayToFluentRuleRector`) stay excluded from the
 * always-run parity gate per the 0.16 spec scoping rationale, but
 * the newly-unlocked path stresses parent/child merge boundaries
 * that warrant behavioral coverage. The fixture pins the before/
 * after rule sets as runtime-equivalent.
 */
return [
    'rules_before' => [
        'name' => 'required|string|max:255',
        'email' => 'required|email',
    ],
    'rules_after' => [
        'name' => FluentRule::string()->required()->max(255),
        'email' => FluentRule::string()->required()->email(),
    ],
    'payloads' => [
        'missing all' => [],
        'valid both' => ['name' => 'Alice', 'email' => 'alice@example.test'],
        'name too long' => ['name' => str_repeat('a', 300), 'email' => 'a@b.test'],
        'email malformed' => ['name' => 'Alice', 'email' => 'not-an-email'],
        'name integer' => ['name' => 42, 'email' => 'a@b.test'],
    ],
];
