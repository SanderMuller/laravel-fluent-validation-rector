<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Livewire\Attributes\Validate;
use Livewire\Component;

// Variable `$this->validate($rules)` arg — can't inspect the key set
// statically. Classwide bail.

final class VariableRulesPage extends Component
{
    #[Validate('required|string')]
    public string $title = '';

    public function save(): void
    {
        $rules = ['title' => 'required|max:150'];
        $this->validate($rules);
    }
}

?>
