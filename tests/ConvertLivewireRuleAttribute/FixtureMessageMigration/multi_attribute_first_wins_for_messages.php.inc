<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class MultiAttributeFirstWinsForMessages extends Component
{
    // Two #[Validate] attributes on one property — the second's rules
    // are discarded (first-wins) by extractAndStripRuleAttribute.
    // Migrating messages from the second too would emit orphan keys
    // for rules that don't exist in the generated rules() method.
    // Message extraction must follow the same first-wins contract:
    // only the first attribute's message: arg becomes a messages()
    // entry, the second is left for the legacy skip-log to surface.
    #[Validate('required|email', message: 'First-attribute message.')]
    #[Validate('min:5', message: 'Second-attribute message — discarded.')]
    public string $email = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

class MultiAttributeFirstWinsForMessages extends Component
{
    // Two #[Validate] attributes on one property — the second's rules
    // are discarded (first-wins) by extractAndStripRuleAttribute.
    // Migrating messages from the second too would emit orphan keys
    // for rules that don't exist in the generated rules() method.
    // Message extraction must follow the same first-wins contract:
    // only the first attribute's message: arg becomes a messages()
    // entry, the second is left for the legacy skip-log to surface.
    #[Validate]
    public string $email = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'email' => FluentRule::email()->required(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email' => 'First-attribute message.',
        ];
    }
}

?>
