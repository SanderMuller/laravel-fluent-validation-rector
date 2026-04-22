<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class AnchorsOnFirstConvertibleNotFirstPresent extends Component
{
    // Codex regression pin: extractAndStripRuleAttribute is
    // first-CONVERTIBLE-wins (skips attributes whose first arg isn't
    // String_/Array_). collectMessagesForProperty must mirror that —
    // anchoring on the first PRESENT Livewire attribute would orphan
    // the second attribute's message: arg, since its rules get migrated
    // and stripped but its messages never reach messages().
    //
    // Here: first attribute has a non-literal first arg (closure),
    // so it's unconvertible. Rule extraction picks the second one.
    // Message extraction must do the same.
    #[Validate(rule: fn () => 'required', message: 'Skipped — unconvertible shape.')]
    #[Validate('required|email', message: 'This is the migrated message.')]
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

class AnchorsOnFirstConvertibleNotFirstPresent extends Component
{
    // Codex regression pin: extractAndStripRuleAttribute is
    // first-CONVERTIBLE-wins (skips attributes whose first arg isn't
    // String_/Array_). collectMessagesForProperty must mirror that —
    // anchoring on the first PRESENT Livewire attribute would orphan
    // the second attribute's message: arg, since its rules get migrated
    // and stripped but its messages never reach messages().
    //
    // Here: first attribute has a non-literal first arg (closure),
    // so it's unconvertible. Rule extraction picks the second one.
    // Message extraction must do the same.
    #[Validate(rule: fn () => 'required', message: 'Skipped — unconvertible shape.')]
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
            'email' => 'This is the migrated message.',
        ];
    }
}

?>
