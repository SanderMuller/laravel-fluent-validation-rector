<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use Livewire\Component;

final class ArraySimpleAttributeComponent extends Component
{
    #[Rule(['required', 'string', 'max:255'])]
    public string $name = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Rule;
use Livewire\Component;

final class ArraySimpleAttributeComponent extends Component
{
    public string $name = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
