<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRules;

// Layer-1 regression fixture. The class does NOT extend FormRequest,
// does NOT use the HasFluent* traits, is NOT Livewire, and is NOT
// a FluentValidator subclass. Its ONLY would-be qualifier is the
// misapplied `#[FluentRules]` on the denylisted `casts()` method.
// Layer 1 (in `QualifiesForRulesProcessing::hasFluentRulesAttribute
// OnAnyMethod()`) skips denylisted method names before checking the
// attribute, so the class does NOT qualify. Auto-detect must NOT
// scan the sibling `helperWithRulesShape()`.
class MisappliedAttributeOnlyClass
{
    #[FluentRules]
    public function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function helperWithRulesShape(): array
    {
        return [
            'subject' => 'required|string',
        ];
    }
}

?>
