<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

final class KeyedArraySingleEntry extends Component
{
    #[Validate(['todos' => 'required|string'])]
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

final class KeyedArraySingleEntry extends Component
{
    #[Validate]
    public array $todos = [];

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'todos' => FluentRule::string()->required(),
        ];
    }
}

?>
