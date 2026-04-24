<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;

// Regression: a Livewire component that has a rules() method but no FluentRule
// usage (all string rules) used to be silently dropped by this rector. It now
// emits a skip-log entry pointing at ValidationStringToFluentRuleRector so
// users notice the pipeline ordering issue. The fixture itself is a no-change
// because this rector only adds the trait when FluentRule is already present.
class EditUserWithStringRules extends Component
{
    public string $name = '';

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }

    public function render()
    {
        return view('livewire.edit-user');
    }
}

?>
