<?php declare(strict_types=1);

/**
 * Coverage gate: every semantics-changing in-scope rector must ship at least
 * one parity fixture. Future widenings to in-scope rectors must add fixtures
 * for the new shapes; this test fails CI if a rector is added to the in-scope
 * list without coverage.
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
