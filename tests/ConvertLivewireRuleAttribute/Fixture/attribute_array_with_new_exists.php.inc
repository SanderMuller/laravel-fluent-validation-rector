<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Illuminate\Validation\Rules\Exists;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class AttributeArrayWithNewExistsComponent extends Component
{
    #[Validate(['required', 'string', new Exists('roles', 'name')])]
    public string $role = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Exists;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class AttributeArrayWithNewExistsComponent extends Component
{
    #[Validate]
    public string $role = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'role' => FluentRule::string()->required()->exists('roles', 'name'),
        ];
    }
}

?>
