<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

final class MergedRulesComponent extends Component
{
    #[Rule('required|string', as: 'name')]
    public string $name = '';

    public string $email = '';

    protected function rules(): array
    {
        return [
            'email' => FluentRule::email()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

final class MergedRulesComponent extends Component
{
    public string $name = '';

    public string $email = '';

    protected function rules(): array
    {
        return [
            'email' => FluentRule::email()->required(), 'name' => FluentRule::string()->required()->label('name'),
        ];
    }
}

?>
