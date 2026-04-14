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
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class ArrayRuleUniqueAttributeComponent extends Component
{
    public string $email = '';

    /**
     * @return array<string, FluentRule>
     */
    protected function rules(): array
    {
        return [
            'email' => FluentRule::email()->required()->rule(new Unique('users', 'email')),
        ];
    }
}

?>
