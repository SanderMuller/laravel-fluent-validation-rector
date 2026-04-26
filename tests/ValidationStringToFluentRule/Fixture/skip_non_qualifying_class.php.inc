<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

// Domain entity with a `rules()` method returning a rules-shaped array.
// Pre-Phase-1 the converter walked any class with such a method,
// regardless of qualification; with the class-qualification gate it
// silently skips classes that don't extend FormRequest, don't use the
// fluent-validation traits, aren't Livewire, and don't carry a
// `#[FluentRules]` attribute. This fixture proves the gate fires
// silently (no transformation, no skip-log noise) — the typical case
// where a Domain entity happens to expose a `rules()` method for
// reasons unrelated to validation.
class DomainEntityWithRulesMethod
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}

?>
