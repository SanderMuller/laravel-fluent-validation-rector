<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Support\Facades\Validator;
use SanderMuller\FluentValidation\FluentRule;

// Non-FormRequest, non-Livewire class that uses FluentRule inside a
// standalone `Validator::make([...])->validate()` call. The widened
// trait-rector (0.14.0) walked every method and matched the FluentRule
// usage, then added the FormRequest-internal `HasFluentRules` trait —
// which overrides `createDefaultValidator(ValidationFactory)` on
// FormRequest. On a non-FormRequest the override is dead code at best
// and misleading at worst (collectiq dogfood, 2026-04-26). The trait
// must only be added when the class is on the FormRequest ancestor
// chain (or in the configured `base_classes` list).
final readonly class ResetUserPassword
{
    public function reset(array $input): void
    {
        Validator::make($input, [
            'password' => FluentRule::password()->required()->confirmed(),
        ])->validate();
    }
}

?>
