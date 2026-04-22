<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class ArrayMessageWithPathKeysPreservesVerbatim extends Component
{
    #[Validate(['todos' => 'required|array', 'todos.*' => 'required|min:3'], message: [
        'todos.required' => 'Add at least one todo.',
        'todos.*.required' => 'Each todo is required.',
        'todos.*.min' => 'Each todo must be at least 3 characters.',
    ])]
    public array $todos = [];
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ArrayMessageWithPathKeysPreservesVerbatim extends Component
{
    #[Validate]
    public array $todos = [];

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'todos' => FluentRule::array()->required(),
            'todos.*' => FluentRule::array()->required()->min(3),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'todos.required' => 'Add at least one todo.',
            'todos.*.required' => 'Each todo is required.',
            'todos.*.min' => 'Each todo must be at least 3 characters.',
        ];
    }
}

?>
