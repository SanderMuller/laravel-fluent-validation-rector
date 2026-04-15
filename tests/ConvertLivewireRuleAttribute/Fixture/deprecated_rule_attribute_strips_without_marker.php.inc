<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use Livewire\Component;

/**
 * Regression pin for Phase 2: the deprecated `#[Rule]` attribute form does
 * NOT trigger real-time-validation marker preservation. The rector's scope
 * is FluentRule migration, not upgrading consumers from `#[Rule]` to the
 * current `#[Validate]` form. A user who hasn't yet moved to `#[Validate]`
 * is signaling they're on an older pattern; auto-adding a `#[Validate]`
 * marker would be a surprising semantic upgrade.
 */
final class DeprecatedRuleAttributeComponent extends Component
{
    #[Rule('required|string|max:255')]
    public string $name = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Rule;
use Livewire\Component;

/**
 * Regression pin for Phase 2: the deprecated `#[Rule]` attribute form does
 * NOT trigger real-time-validation marker preservation. The rector's scope
 * is FluentRule migration, not upgrading consumers from `#[Rule]` to the
 * current `#[Validate]` form. A user who hasn't yet moved to `#[Validate]`
 * is signaling they're on an older pattern; auto-adding a `#[Validate]`
 * marker would be a surprising semantic upgrade.
 */
final class DeprecatedRuleAttributeComponent extends Component
{
    public string $name = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
