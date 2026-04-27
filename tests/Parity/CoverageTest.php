<?php declare(strict_types=1);

/**
 * Coverage gate: every semantics-changing in-scope rector must ship at least
 * one parity fixture. Future widenings to in-scope rectors must add fixtures
 * for the new shapes; this test fails CI if a rector is added to the in-scope
 * list without coverage.
 *
 * Plus the `Attributed/` sibling directory: parity coverage for the
 * `#[FluentRules]` opt-in paths unlocked in 0.16. The host converter rectors
 * (`ValidationStringToFluentRuleRector` / `ValidationArrayToFluentRuleRector`)
 * stay excluded from the always-run gate per the 0.16 spec scoping
 * rationale, but the newly-unlocked attributed-method paths stress
 * parent/child merge boundaries that warrant behavioral coverage. The
 * Attributed dir is the documented home for those fixtures.
 */
test('every semantics-changing rector has parity coverage', function (string $rectorName): void {
    $fixtures = glob(__DIR__ . '/Fixture/' . $rectorName . '/*.php');

    expect($fixtures)
        ->not->toBeFalse()
        ->and($fixtures)->not->toBeEmpty(
            "Rector '{$rectorName}' is in the semantics-changing in-scope list "
            . 'but ships zero parity fixtures under '
            . "tests/Parity/Fixture/{$rectorName}/. Author at least one fixture "
            . 'per the §3 fixture list, or remove the rector from the in-scope set.',
        );
})->with([
    'SimplifyRuleWrappersRector',
    'GroupWildcardRulesToEachRector',
    'PromoteFieldFactoryRector',
]);

test('Attributed-path parity coverage exists', function (): void {
    $fixtures = glob(__DIR__ . '/Fixture/Attributed/*.php');

    expect($fixtures)
        ->not->toBeFalse()
        ->and($fixtures)->not->toBeEmpty(
            'tests/Parity/Fixture/Attributed/ must ship at least one fixture '
            . 'covering the #[FluentRules] opt-in paths unlocked in 0.16. '
            . 'Either author one or remove the directory.',
        );
});

test('parity Fixture/ has only documented subdirectories', function (): void {
    $allowed = [
        'SimplifyRuleWrappersRector',
        'GroupWildcardRulesToEachRector',
        'PromoteFieldFactoryRector',
        'Attributed',
    ];

    $entries = glob(__DIR__ . '/Fixture/*', GLOB_ONLYDIR);
    expect($entries)->not->toBeFalse();

    $present = array_map(basename(...), $entries === false ? [] : $entries);
    $undocumented = array_values(array_diff($present, $allowed));

    expect($undocumented)->toBe(
        [],
        'Unexpected subdirectories under tests/Parity/Fixture/: '
        . implode(', ', $undocumented)
        . '. Either add the directory to the documented list (and ship a '
        . 'corresponding coverage assertion) or remove it. Excluded rectors '
        . '(string/array converters, trait-add, Livewire-attribute, inline-*, '
        . 'docblock, simplify-fluent) MUST NOT have a Fixture/ subdir of their '
        . 'own — their parity coverage lives under Attributed/.',
    );
});
