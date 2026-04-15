<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

final class KeyedArrayWithListArrayValues extends Component
{
    #[Validate([
        'todos' => ['required', 'array'],
        'todos.*' => ['string', 'min:3'],
    ])]
    public array $todos = [];
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class KeyedArrayWithListArrayValues extends Component
{
    public array $todos = [];

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'todos' => FluentRule::array()->required(),
            'todos.*' => FluentRule::string()->min(3),
        ];
    }
}

?>
