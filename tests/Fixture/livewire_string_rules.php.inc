<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Livewire\Component;

class EditProfile extends Component
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Component;

class EditProfile extends Component
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
            'bio' => FluentRule::string()->nullable(),
        ];
    }
}

?>
