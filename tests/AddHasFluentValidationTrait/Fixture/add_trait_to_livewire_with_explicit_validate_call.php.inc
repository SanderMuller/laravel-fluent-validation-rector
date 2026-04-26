<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

// Positive: Livewire component calling `$this->validate([...])` with
// inline FluentRule entries — no `rules()` method, no `#[Validate]`
// attribute. The Livewire-side validation surface IS exercised, so
// the trait must be added (its overrides apply to `$this->validate`).
// Pairs with `skip_livewire_only_uses_standalone_validator` —
// confirms the new gate doesn't over-narrow.
class SaveDraft extends Component
{
    public string $title = '';

    public function save(): void
    {
        $this->validate([
            'title' => FluentRule::string()->required()->max(255),
        ]);
    }

    public function render()
    {
        return view('livewire.save-draft');
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

// Positive: Livewire component calling `$this->validate([...])` with
// inline FluentRule entries — no `rules()` method, no `#[Validate]`
// attribute. The Livewire-side validation surface IS exercised, so
// the trait must be added (its overrides apply to `$this->validate`).
// Pairs with `skip_livewire_only_uses_standalone_validator` —
// confirms the new gate doesn't over-narrow.
class SaveDraft extends Component
{
    use HasFluentValidation;

    public string $title = '';

    public function save(): void
    {
        $this->validate([
            'title' => FluentRule::string()->required()->max(255),
        ]);
    }

    public function render()
    {
        return view('livewire.save-draft');
    }
}

?>
