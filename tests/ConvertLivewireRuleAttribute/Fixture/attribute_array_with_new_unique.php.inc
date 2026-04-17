<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Illuminate\Validation\Rules\Unique;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class AttributeArrayWithNewUniqueComponent extends Component
{
    #[Validate(['required', 'string', new Unique('users', 'email')])]
    public string $email = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Unique;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class AttributeArrayWithNewUniqueComponent extends Component
{
    #[Validate]
    public string $email = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'email' => FluentRule::string()->required()->unique('users', 'email'),
        ];
    }
}

?>
