<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class ArrayRuleUniqueAttributeComponent extends Component
{
    #[Validate(['required', 'email', new Rule\Unique('users', 'email')])]
    public string $email = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Validation\Rule\Unique;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class ArrayRuleUniqueAttributeComponent extends Component
{
    #[Validate]
    public string $email = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'email' => FluentRule::email()->required()->rule(new Unique('users', 'email')),
        ];
    }
}

?>
