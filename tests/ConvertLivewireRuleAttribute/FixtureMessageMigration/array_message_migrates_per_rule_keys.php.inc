<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class ArrayMessageMigratesPerRuleKeys extends Component
{
    #[Validate('required|min:3', message: [
        'required' => 'Name is required.',
        'min' => 'Name must be at least 3 characters.',
    ])]
    public string $name = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ArrayMessageMigratesPerRuleKeys extends Component
{
    #[Validate]
    public string $name = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->min(3),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'name.min' => 'Name must be at least 3 characters.',
        ];
    }
}

?>
