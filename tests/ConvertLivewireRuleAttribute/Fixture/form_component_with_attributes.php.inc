<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Form;

final class UserForm extends Form
{
    #[Validate('required|string|max:255', as: 'user name')]
    public string $name = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Validate;
use Livewire\Form;

final class UserForm extends Form
{
    public string $name = '';

    /**
     * @return array<string, FluentRule|string|array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255)->label('user name'),
        ];
    }
}

?>
