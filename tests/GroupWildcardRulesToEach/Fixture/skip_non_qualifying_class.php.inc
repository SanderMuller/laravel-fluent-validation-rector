<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Domain entity with a wildcard-rules-shaped `rules()` method.
// Class-qualification gate (Phase 1) silently skips — the class
// doesn't extend FormRequest, doesn't use the fluent-validation
// traits, isn't Livewire, and has no `#[FluentRules]` attribute.
class DomainEntityWithWildcardRules
{
    public function rules(): array
    {
        return [
            'items' => FluentRule::array()->required(),
            'items.*.name' => FluentRule::string()->required(),
        ];
    }
}

?>
