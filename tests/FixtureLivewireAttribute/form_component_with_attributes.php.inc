<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

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

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Validate;
use Livewire\Form;

final class UserForm extends Form
{
    public string $name = '';
    protected function rules(): array
    {
        return ['name' => FluentRule::string()->required()->max(255)->label('user name')];
    }
}

?>
