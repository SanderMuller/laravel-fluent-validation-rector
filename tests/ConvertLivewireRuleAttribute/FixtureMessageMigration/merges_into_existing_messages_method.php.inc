<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class MergesIntoExistingMessagesMethod extends Component
{
    #[Validate('required|email', message: 'Your email is wrong.')]
    public string $email = '';

    protected function messages(): array
    {
        return [
            'name.required' => 'User-authored entry — must be preserved.',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

class MergesIntoExistingMessagesMethod extends Component
{
    #[Validate]
    public string $email = '';

    protected function messages(): array
    {
        return [
            'name.required' => 'User-authored entry — must be preserved.',
            'email' => 'Your email is wrong.',
        ];
    }

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'email' => FluentRule::email()->required(),
        ];
    }
}

?>
