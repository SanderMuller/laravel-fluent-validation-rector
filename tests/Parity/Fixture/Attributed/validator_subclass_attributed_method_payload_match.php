<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Parity coverage for the 1.1 / 0.16 unlock on Validator-subclass
 * methods (e.g. `JsonImportValidator extends FluentValidator`'s
 * `rulesWithoutPrefix()`). The before/after rule sets must be
 * runtime-equivalent — the harness exists precisely to catch
 * behavioral drift the converter's structural tests can't see.
 */
return [
    'rules_before' => [
        'title' => 'required|string|max:120',
        'lang' => 'required|string',
    ],
    'rules_after' => [
        'title' => FluentRule::string()->required()->max(120),
        'lang' => FluentRule::string()->required(),
    ],
    'payloads' => [
        'missing' => [],
        'valid' => ['title' => 'Subject A', 'lang' => 'en'],
        'title too long' => ['title' => str_repeat('x', 200), 'lang' => 'en'],
        'lang integer' => ['title' => 'Subject', 'lang' => 1],
    ],
];
