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
     * } $fixture
     */
    $fixture = require $fixturePath;
    $allowed = $fixture['allowed_divergences'] ?? [];

    $orphanKeys = array_diff(array_keys($allowed), array_keys($fixture['payloads']));
    expect($orphanKeys)->toBe(
        [],
        '`allowed_divergences` keys must reference existing payload names. Orphans: '
        . implode(', ', $orphanKeys),
    );

    $usedDivergenceKeys = [];

    foreach ($fixture['payloads'] as $payloadName => $payload) {
        $outcome = ParityHarness::compare(
            $fixture['rules_before'],
            $fixture['rules_after'],
            $payload,
        );

        if ($outcome->type === ParityType::Skipped) {
            expect($outcome->skipReason)->not->toBeNull();

            continue;
        }

        if ($outcome->type === ParityType::Match) {
            // Match branch: assert no stale divergence entry hides here.
            expect($allowed)->not->toHaveKey(
                $payloadName,
                "Payload '{$payloadName}' produced MATCH but `allowed_divergences` still "
                . 'documents a divergence. Remove the stale entry.',
            );

            continue;
        }

        $allowedEntry = $allowed[$payloadName] ?? null;

        expect($allowedEntry)->not->toBeNull(
            "Payload '{$payloadName}' produced parity outcome {$outcome->type->value} "
            . 'without an `allowed_divergences` entry. Either fix the rector or document the divergence. '
            . 'before: ' . json_encode($outcome->beforeErrors) . ' '
            . 'after: ' . json_encode($outcome->afterErrors),
        );

        expect($allowedEntry['category'])->toBeInstanceOf(DivergenceCategory::class);
        expect($allowedEntry['rationale'])->toBeString()->not->toBeEmpty();

        expect($outcome->type)->toBeIn(
            $allowedEntry['category']->allowedOutcomes(),
            "Payload '{$payloadName}' produced {$outcome->type->value}, which is not in the "
            . "{$allowedEntry['category']->value} category's allowed outcome set.",
        );

        $usedDivergenceKeys[] = $payloadName;
    }
})->with('parity_fixtures');
