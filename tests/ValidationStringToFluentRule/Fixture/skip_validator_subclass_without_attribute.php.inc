<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentValidator;

// Validator subclass with NO `#[FluentRules]` attribute. `extends
// FluentValidator` is NOT a class-wide qualification signal — the
// class does not extend FormRequest, does not use the HasFluent*
// traits, is not Livewire. Auto-detect must NOT scan rules-shaped
// helpers. The class is silently skipped.
class UnattributedValidatorSubclass extends FluentValidator
{
    protected function rulesWithoutPrefix(): array
    {
        return [
            'title' => 'required|string',
        ];
    }

    public function buildPayload(): array
    {
        return [
            'subject' => 'required|string',
        ];
    }
}

?>
