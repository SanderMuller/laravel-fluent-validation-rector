<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Livewire\Attributes\Validate;
use Livewire\Component;

// Codex review must-have: `validate(rules: [...], messages: [...])` with
// the rules arg passed by name. Positional-only extraction would inspect
// `messages` (arg 0 in declaration order — wait, PHP named args preserve
// order regardless). Either way the partial-overlap check must find the
// 'title' key via the named-arg lookup and skip the overlapping attr.

final class NamedRulesArgPage extends Component
{
    #[Validate('required|string|min:6')]
    public string $title = '';

    #[Validate('nullable|string')]
    public ?string $slug = null;

    public function save(): void
    {
        $this->validate(
            messages: ['title.required' => 'Title is required.'],
            rules: ['title' => 'required|max:150'],
        );
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

// Codex review must-have: `validate(rules: [...], messages: [...])` with
// the rules arg passed by name. Positional-only extraction would inspect
// `messages` (arg 0 in declaration order — wait, PHP named args preserve
// order regardless). Either way the partial-overlap check must find the
// 'title' key via the named-arg lookup and skip the overlapping attr.

final class NamedRulesArgPage extends Component
{
    #[Validate('required|string|min:6')]
    public string $title = '';

    #[Validate]
    public ?string $slug = null;

    public function save(): void
    {
        $this->validate(
            messages: ['title.required' => 'Title is required.'],
            rules: ['title' => 'required|max:150'],
        );
    }

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'slug' => FluentRule::string()->nullable(),
        ];
    }
}

?>
