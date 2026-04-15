<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use Livewire\Component;

final class ArrayWithAsLabelAttributeComponent extends Component
{
    #[Rule(['required', 'string'], as: 'display name')]
    public string $displayName = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Rule;
use Livewire\Component;

final class ArrayWithAsLabelAttributeComponent extends Component
{
    public string $displayName = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'displayName' => FluentRule::string()->required()->label('display name'),
        ];
    }
}

?>
