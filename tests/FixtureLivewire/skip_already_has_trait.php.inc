<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewire;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

class AlreadyHasTraitComponent extends Component
{
    use HasFluentValidation;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }

    public function render()
    {
        return view('livewire.component');
    }
}

?>
