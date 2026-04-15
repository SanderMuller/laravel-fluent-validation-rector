<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePreservationDisabled;

use Livewire\Attributes\Validate;
use Livewire\Component;

final class ValidateWithoutMarkerComponent extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePreservationDisabled;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class ValidateWithoutMarkerComponent extends Component
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
