<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Livewire\Attributes\Validate;
use Livewire\Component;

// `array_merge($dynamic, ['title' => ...])` wraps the rule array in a
// call whose full key set we can't statically inspect. Codex review
// requires a classwide bail here regardless of configured mode —
// converting the non-overlapping attr would misclassify it as safe
// since the hidden $dynamic portion could include that property's key.

final class MergedRulesPage extends Component
{
    #[Validate('required|string')]
    public string $title = '';

    #[Validate('nullable|string')]
    public ?string $slug = null;

    private array $extraRules = ['slug' => 'required'];

    public function save(): void
    {
        $this->validate(array_merge($this->extraRules, [
            'title' => 'required|max:150',
        ]));
    }
}

?>
