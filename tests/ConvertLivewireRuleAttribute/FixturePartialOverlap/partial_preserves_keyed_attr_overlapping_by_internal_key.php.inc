<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Livewire\Attributes\Validate;
use Livewire\Component;

// Codex review must-have: `#[Validate(['todos' => ..., 'todos.*' => ...])]`
// is annotated on property `$items`, but the effective rule keys are
// 'todos' / 'todos.*'. If explicit validate() has 'todos', partial mode
// MUST skip this property — checking only property name 'items' would
// incorrectly convert + strip the overlapping rule.

final class KeyedOverlapPage extends Component
{
    #[Validate(['todos' => 'required|array', 'todos.*' => 'string|min:3'])]
    public array $items = [];

    public function save(): void
    {
        $this->validate([
            'todos' => 'required|array',
        ]);
    }
}

?>
