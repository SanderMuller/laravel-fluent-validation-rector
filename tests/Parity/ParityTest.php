<?php declare(strict_types=1);

use SanderMuller\FluentValidationRector\Tests\Parity\DivergenceCategory;
use SanderMuller\FluentValidationRector\Tests\Parity\ParityHarness;
use SanderMuller\FluentValidationRector\Tests\Parity\ParityType;

dataset('parity_fixtures', function () {
    $root = __DIR__ . '/Fixture';
    $paths = glob($root . '/*/*.php');

    foreach ($paths === false ? [] : $paths as $path) {
        $key = basename(dirname($path)) . '/' . basename($path, '.php');

        yield $key => [$path];
    }
});

test('parity holds for fixture', function (string $fixturePath): void {
    /**
     * @var array{
     *     rules_before: array<string, mixed>,
     *     rules_after: array<string, mixed>,
     *     payloads: array<string, array<string, mixed>>,
     *     allowed_divergences?: array<string, array{category: DivergenceCategory, rationale: string}>,
     *     skip_compiled?: string,
     * }|array{skip: string} $fixture
     */
    $fixture = require $fixturePath;

    // A fixture returns `['skip' => reason]` before it builds any rule when the
    // installed laravel-fluent-validation lacks a method it calls, or when the
    // rector does not apply the conversion on that version.
    if (isset($fixture['skip'])) {
        $this->markTestSkipped($fixture['skip']);
    }

    $allowed = $fixture['allowed_divergences'] ?? [];

    $orphanKeys = array_diff(array_keys($allowed), array_keys($fixture['payloads']));
    expect($orphanKeys)->toBe(
        [],
        '`allowed_divergences` keys must reference existing payload names. Orphans: '
        . implode(', ', $orphanKeys),
    );

    $usedDivergenceKeys = [];

    foreach ($fixture['payloads'] as $payloadName => $payload) {
        $diverged = false;

        // Run each payload twice: with every fluent rule as a self-validating
        // object, and compiled the way `HasFluentRules` compiles it. The two
        // paths order rules differently, so a pair can match on one and not
        // on the other.
        // A fixture sets `skip_compiled` with a reason when the prepare step
        // cannot run its payloads.
        $modes = isset($fixture['skip_compiled']) ? ['object' => false] : ['object' => false, 'compiled' => true];

        foreach ($modes as $mode => $compiled) {
            $outcome = ParityHarness::compare(
                $fixture['rules_before'],
                $fixture['rules_after'],
                $payload,
                $compiled,
            );

            if ($outcome->type === ParityType::Skipped) {
                expect($outcome->skipReason)->not->toBeNull();

                continue;
            }

            if ($outcome->type === ParityType::Match) {
                continue;
            }

            $diverged = true;
            $allowedEntry = $allowed[$payloadName] ?? null;

            expect($allowedEntry)->not->toBeNull(
                "Payload '{$payloadName}' ({$mode}) produced parity outcome {$outcome->type->value} "
                . 'without an `allowed_divergences` entry. Either fix the rector or document the divergence. '
                . 'before: ' . json_encode($outcome->beforeErrors) . ' '
                . 'after: ' . json_encode($outcome->afterErrors),
            );

            expect($allowedEntry['category'])->toBeInstanceOf(DivergenceCategory::class);
            expect($allowedEntry['rationale'])->toBeString()->not->toBeEmpty();

            expect($outcome->type)->toBeIn(
                $allowedEntry['category']->allowedOutcomes(),
                "Payload '{$payloadName}' ({$mode}) produced {$outcome->type->value}, which is not in the "
                . "{$allowedEntry['category']->value} category's allowed outcome set.",
            );
        }

        if ($diverged) {
            $usedDivergenceKeys[] = $payloadName;
        }
    }

    // An entry is stale when the payload matches in both modes.
    $staleKeys = array_diff(array_keys($allowed), $usedDivergenceKeys);
    expect($staleKeys)->toBe(
        [],
        'Payloads produced MATCH in both modes but `allowed_divergences` still documents a '
        . 'divergence. Remove the stale entries: ' . implode(', ', $staleKeys),
    );
})->with('parity_fixtures');
