<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

// Domain entity with a `rules()` method returning array-form rules.
// Class-qualification gate (Phase 1) silently skips this — see
// QualifiesForRulesProcessing for the rationale.
class DomainEntityWithArrayRulesMethod
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

?>
