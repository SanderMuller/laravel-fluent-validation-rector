<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Regression pin from Phase 2 codex review: when a property has multiple
 * `#[Validate]` attributes and any one of them carries `onUpdate: false`,
 * the rector's first-wins extraction used to lock in marker preservation
 * based on the first attribute alone, silently re-enabling real-time
 * validation that the user explicitly disabled on a later attribute.
 *
 * The fix scans all `#[Validate]` attributes on the property before the
 * strip loop and vetoes marker emission if any opts out of real-time.
 * Rule-extraction first-wins semantics are unchanged — only the marker
 * decision aggregates across all attributes.
 */
final class MultipleValidateWithOptOutComponent extends Component
{
    #[Validate('required')]
    #[Validate('max:10', onUpdate: false)]
    public string $name = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Regression pin from Phase 2 codex review: when a property has multiple
 * `#[Validate]` attributes and any one of them carries `onUpdate: false`,
 * the rector's first-wins extraction used to lock in marker preservation
 * based on the first attribute alone, silently re-enabling real-time
 * validation that the user explicitly disabled on a later attribute.
 *
 * The fix scans all `#[Validate]` attributes on the property before the
 * strip loop and vetoes marker emission if any opts out of real-time.
 * Rule-extraction first-wins semantics are unchanged — only the marker
 * decision aggregates across all attributes.
 */
final class MultipleValidateWithOptOutComponent extends Component
{
    public string $name = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
