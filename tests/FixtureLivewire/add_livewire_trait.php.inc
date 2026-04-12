<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewire;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

class EditUser extends Component
{
    public string $name = '';

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }

    public function render()
    {
        return view('livewire.edit-user');
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewire;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

class EditUser extends Component
{
    use HasFluentValidation;

    public string $name = '';

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }

    public function render()
    {
        return view('livewire.edit-user');
    }
}

?>
