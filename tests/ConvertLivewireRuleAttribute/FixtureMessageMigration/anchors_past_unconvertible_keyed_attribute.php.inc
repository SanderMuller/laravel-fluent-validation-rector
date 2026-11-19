<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class AnchorsPastUnconvertibleKeyedAttribute extends Component
{
    // The keyed first attribute holds a type token whose arguments have no
    // fluent equivalent, so it stays and the messages come from the second.
    #[Validate(['items' => 'required|array:name,email'], message: 'Skipped — unconvertible type token.')]
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

class AnchorsPastUnconvertibleKeyedAttribute extends Component
{
    // The keyed first attribute holds a type token whose arguments have no
    // fluent equivalent, so it stays and the messages come from the second.
    #[Validate(['items' => 'required|array:name,email'], message: 'Skipped — unconvertible type token.')]
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
