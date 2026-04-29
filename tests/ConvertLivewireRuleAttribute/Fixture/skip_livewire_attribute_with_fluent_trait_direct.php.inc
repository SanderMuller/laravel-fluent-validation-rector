<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use SanderMuller\FluentValidation\HasFluentValidation;

// 1.2.0 fixture: direct-trait variant of the compose-conflict shape.
// Class uses `HasFluentValidation` directly (not via abstract
// ancestor) AND has property-level `#[Rule]` attribute. Same silent-
// ignore mechanism as the inherited shape; the rector emits the
// same skip-log entry.
//
// Pin pairs with the inherited variant
// (skip_livewire_attribute_with_fluent_trait_inherited.php.inc) to
// pin BOTH detection paths — direct trait use + ancestor trait use.
//
// No `-----` separator: input == output (skip fixture).
//
// Spec reference: specs/1.2.0-livewire-fluent-trait-compose-warning.md §4.1.
final class DirectTraitLivewireComponent
{
    use HasFluentValidation;

    #[Rule(['required', 'string', 'min:10'])]
    public ?string $phoneNumber = null;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'otherField' => ['required', 'string'],
        ];
    }
}

?>
