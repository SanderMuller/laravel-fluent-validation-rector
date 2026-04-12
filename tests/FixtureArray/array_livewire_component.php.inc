<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Livewire\Component;

class InviteForm extends Component
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'accepted' => ['required', 'boolean'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Component;

class InviteForm extends Component
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->required(),
            'accepted' => FluentRule::boolean()->required(),
        ];
    }
}

?>
