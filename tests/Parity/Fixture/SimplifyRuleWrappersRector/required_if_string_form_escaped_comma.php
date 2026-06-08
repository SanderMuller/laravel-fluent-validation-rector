<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Guards the P2 concern from codex review: string-form parsing splits the
 * rule tail with `explode(',')`, but the fluent method re-serializes with
 * `implode(',')` — an exact inverse — so the emitted rule string is
 * byte-identical to the original regardless of escaped/quoted commas. Laravel
 * then parses the identical string identically, so validation is unchanged.
 *
 * Here `other` carries an escaped comma; before and after must validate the
 * same for every payload.
 */
return [
    'rules_before' => [
        'flag' => FluentRule::string()->nullable()->rule('required_if:other,foo\,bar'),
        'other' => FluentRule::string()->nullable(),
    ],
    // The rector splits the tail on every comma, so the escaped-comma value
    // arrives as two args ('foo\\' and 'bar'); `serializeValues()` re-joins
    // them with a comma, reproducing the original `required_if:other,foo\,bar`
    // rule string byte-for-byte.
    'rules_after' => [
        'flag' => FluentRule::string()->nullable()->requiredIf('other', 'foo\\', 'bar'),
        'other' => FluentRule::string()->nullable(),
    ],
    'payloads' => [
        'other matches value' => ['other' => 'foo\,bar'],
        'other present, different value' => ['other' => 'something-else'],
        'other absent' => [],
        'flag present, other matches' => ['flag' => 'x', 'other' => 'foo\,bar'],
    ],
];
