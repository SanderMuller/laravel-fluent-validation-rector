<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;

class NativeRulesComponent extends Component
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }

    public function render()
    {
        return view('livewire.component');
    }
}

?>
