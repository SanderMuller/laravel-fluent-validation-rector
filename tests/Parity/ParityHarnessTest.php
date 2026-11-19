<?php declare(strict_types=1);

use Illuminate\Support\Facades\App;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\Parity\ParityHarness;
use SanderMuller\FluentValidationRector\Tests\Parity\ParityType;

test('field-iteration order does not affect match outcome', function (): void {
    // Same rules, different declaration order. ksort normalization → MATCH.
    $outcome = ParityHarness::compare(
        ['name' => 'required', 'email' => 'required'],
        ['email' => 'required', 'name' => 'required'],
        [],
    );

    expect($outcome->type)->toBe(ParityType::Match);
});

test('intra-field message order produces BothRejectDifferentOrder', function (): void {
    // Same field, same rules-as-set, different rule declaration order.
    // Laravel runs rules in declared order → message arrays differ in order only.
    $outcome = ParityHarness::compare(
        ['x' => 'string|min:5'],
        ['x' => 'min:5|string'],
        ['x' => 1],
    );

    expect($outcome->type)->toBe(ParityType::BothRejectDifferentOrder);
});

test('outcome MATCH reached on identical errors', function (): void {
    $outcome = ParityHarness::compare(
        ['x' => 'required'],
        ['x' => 'required'],
        [],
    );

    expect($outcome->type)->toBe(ParityType::Match);
});

test('outcome BeforeRejectsAfterPasses reached when only before fails', function (): void {
    $outcome = ParityHarness::compare(
        ['x' => 'required'],
        ['x' => 'sometimes'],
        [],
    );

    expect($outcome->type)->toBe(ParityType::BeforeRejectsAfterPasses);
});

test('outcome AfterRejectsBeforePasses reached when only after fails', function (): void {
    $outcome = ParityHarness::compare(
        ['x' => 'sometimes'],
        ['x' => 'required'],
        [],
    );

    expect($outcome->type)->toBe(ParityType::AfterRejectsBeforePasses);
});

test('outcome BothRejectDifferentMessages reached when message text differs', function (): void {
    // Different rule names → different message text on same fail.
    $outcome = ParityHarness::compare(
        ['x' => 'string'],
        ['x' => 'integer'],
        ['x' => []],
    );

    expect($outcome->type)->toBe(ParityType::BothRejectDifferentMessages);
});

test('DB rule "exists" in rules_before triggers Skipped outcome', function (): void {
    $outcome = ParityHarness::compare(
        ['email' => 'required|exists:users,email'],
        ['email' => 'required'],
        ['email' => 'a@b.test'],
    );

    expect($outcome->type)->toBe(ParityType::Skipped);
    expect($outcome->skipReason)->toContain('exists');
});

test('DB rule "unique" in rules_after triggers Skipped outcome', function (): void {
    $outcome = ParityHarness::compare(
        ['email' => 'required'],
        ['email' => 'required|unique:users,email'],
        ['email' => 'a@b.test'],
    );

    expect($outcome->type)->toBe(ParityType::Skipped);
    expect($outcome->skipReason)->toContain('unique');
});

test('Closure inside rule array triggers Skipped outcome', function (): void {
    $outcome = ParityHarness::compare(
        ['x' => ['required', fn ($attr, $value, $fail) => $fail('nope')]],
        ['x' => ['required']],
        ['x' => 'value'],
    );

    expect($outcome->type)->toBe(ParityType::Skipped);
    expect($outcome->skipReason)->toContain('closure');
});

test('locale is pinned to en regardless of pre-call setLocale', function (): void {
    App::setLocale('fr');

    ParityHarness::compare(
        ['x' => 'required'],
        ['x' => 'required'],
        [],
    );

    expect(App::getLocale())->toBe('en');
});

test('compiled mode applies the labels that RuleSet::prepare() extracts', function (): void {
    // A label on `y` changes the :values text in the error on `x` only when
    // the rules are prepared the way HasFluentRules prepares them. A
    // self-validating object does not pass its label to other fields.
    $labelled = [
        'x' => FluentRule::string()->requiredWith('y'),
        'y' => FluentRule::string()->label('Backup value'),
    ];
    $unlabelled = [
        'x' => FluentRule::string()->requiredWith('y'),
        'y' => FluentRule::string(),
    ];
    $payload = ['y' => 'a'];

    $object = ParityHarness::compare($unlabelled, $labelled, $payload);
    $compiled = ParityHarness::compare($unlabelled, $labelled, $payload, compiled: true);

    expect($object->type)->toBe(ParityType::Match)
        ->and($compiled->type)->toBe(ParityType::BothRejectDifferentMessages)
        ->and($compiled->afterErrors)->toBe(['x' => ['The x field is required when Backup value is present.']]);
});
