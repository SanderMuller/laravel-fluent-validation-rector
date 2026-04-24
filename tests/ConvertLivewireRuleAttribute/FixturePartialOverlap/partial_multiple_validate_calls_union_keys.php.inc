<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Livewire\Attributes\Validate;
use Livewire\Component;

// Two action methods each call $this->validate() with their own rule arrays.
// Overlap set is the union of both key sets. Attrs on `title` and `body` stay
// (both overlap at least one call); `summary` attr converts (not overlapped).

final class MultipleValidateCallsPage extends Component
{
    #[Validate('required|string')]
    public string $title = '';

    #[Validate('required|string')]
    public string $body = '';

    #[Validate('nullable|string')]
    public ?string $summary = null;

    public function saveDraft(): void
    {
        $this->validate([
            'title' => 'required|max:60',
        ]);
    }

    public function publish(): void
    {
        $this->validate([
            'body' => 'required|min:100',
        ]);
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

// Two action methods each call $this->validate() with their own rule arrays.
// Overlap set is the union of both key sets. Attrs on `title` and `body` stay
// (both overlap at least one call); `summary` attr converts (not overlapped).

final class MultipleValidateCallsPage extends Component
{
    #[Validate('required|string')]
    public string $title = '';

    #[Validate('required|string')]
    public string $body = '';

    #[Validate]
    public ?string $summary = null;

    public function saveDraft(): void
    {
        $this->validate([
            'title' => 'required|max:60',
        ]);
    }

    public function publish(): void
    {
        $this->validate([
            'body' => 'required|min:100',
        ]);
    }

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'summary' => FluentRule::string()->nullable(),
        ];
    }
}

?>
