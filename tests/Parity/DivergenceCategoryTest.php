<?php declare(strict_types=1);

use SanderMuller\FluentValidationRector\Tests\Parity\DivergenceCategory;
use SanderMuller\FluentValidationRector\Tests\Parity\ParityType;

test('every category maps to at least one allowed outcome', function (DivergenceCategory $category): void {
    expect($category->allowedOutcomes())
        ->toBeArray()
        ->not->toBeEmpty()
        ->each->toBeInstanceOf(ParityType::class);
})->with(DivergenceCategory::cases());

test('ImplicitTypeConstraint allows AfterRejectsBeforePasses only', function (): void {
    // Promotion-induced implicit type constraints can only narrow the accepted
    // value set. The reverse outcome (before rejects, after passes) would
    // signal a regression making the rule more permissive — must NOT be slotted
    // into this category.
    expect(DivergenceCategory::ImplicitTypeConstraint->allowedOutcomes())
        ->toBe([ParityType::AfterRejectsBeforePasses]);
});

test('MessageKeyDrift allows BothRejectDifferentMessages', function (): void {
    expect(DivergenceCategory::MessageKeyDrift->allowedOutcomes())
        ->toContain(ParityType::BothRejectDifferentMessages);
});

test('AttributeLabelDrift allows BothRejectDifferentMessages', function (): void {
    expect(DivergenceCategory::AttributeLabelDrift->allowedOutcomes())
        ->toContain(ParityType::BothRejectDifferentMessages);
});

test('OrderDependentPipeline allows BothRejectDifferentOrder', function (): void {
    expect(DivergenceCategory::OrderDependentPipeline->allowedOutcomes())
        ->toContain(ParityType::BothRejectDifferentOrder);
});
