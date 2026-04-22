<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class StringMessageMigratesToAttributeKey extends Component
{
    #[Validate('required|email', message: 'Your email is wrong.')]
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

class StringMessageMigratesToAttributeKey extends Component
{
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
            'email' => 'Your email is wrong.',
        ];
    }
}

?>
