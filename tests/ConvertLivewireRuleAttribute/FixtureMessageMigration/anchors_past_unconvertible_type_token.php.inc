<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class AnchorsPastUnconvertibleTypeToken extends Component
{
    // A type token with arguments that have no fluent equivalent keeps the
    // first attribute, so the messages come from the second one.
    #[Validate('required|array:name,email', message: 'Skipped — unconvertible type token.')]
    #[Validate('required|array', message: 'This is the migrated message.')]
    public array $items = [];
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AnchorsPastUnconvertibleTypeToken extends Component
{
    // A type token with arguments that have no fluent equivalent keeps the
    // first attribute, so the messages come from the second one.
    #[Validate('required|array:name,email', message: 'Skipped — unconvertible type token.')]
    #[Validate]
    public array $items = [];

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'items' => FluentRule::array()
                ->required(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'items' => 'This is the migrated message.',
        ];
    }
}

?>
