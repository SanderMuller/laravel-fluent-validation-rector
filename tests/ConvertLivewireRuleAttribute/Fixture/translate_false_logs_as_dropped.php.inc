<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase 3: `translate: false` disables Livewire's `trans()` call on the
 * attribute's messages. FluentRule has no equivalent — the setting gets
 * dropped and skip-logged. The attribute's rule still converts; the
 * real-time marker is preserved per Phase 2 (no `onUpdate: false` here).
 */
final class TranslateFalseComponent extends Component
{
    #[Validate('required|string', translate: false)]
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
 * Phase 3: `translate: false` disables Livewire's `trans()` call on the
 * attribute's messages. FluentRule has no equivalent — the setting gets
 * dropped and skip-logged. The attribute's rule still converts; the
 * real-time marker is preserved per Phase 2 (no `onUpdate: false` here).
 */
final class TranslateFalseComponent extends Component
{
    #[Validate]
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
