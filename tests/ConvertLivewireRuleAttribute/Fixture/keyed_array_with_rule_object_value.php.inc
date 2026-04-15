<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class KeyedArrayWithRuleObjectValue extends Component
{
    #[Validate([
        'password' => ['required', new Password(8)],
    ])]
    public string $password = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class KeyedArrayWithRuleObjectValue extends Component
{
    #[Validate]
    public string $password = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'password' => FluentRule::field()->required()->rule(new Password(8)),
        ];
    }
}

?>
