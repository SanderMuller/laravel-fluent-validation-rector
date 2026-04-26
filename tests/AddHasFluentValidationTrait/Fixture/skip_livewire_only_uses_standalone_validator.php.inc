<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

// Livewire component that uses FluentRule, but ONLY inside a standalone
// `Validator::make([...])->validate()` call — never via Livewire's
// own validation entry points (`$this->validate(...)`,
// `$this->validateOnly(...)`, `#[Validate]` attribute on a property,
// or a `rules()` method). The widened trait-rector (0.14.0) walked
// every method and matched the FluentRule usage, then added
// `HasFluentValidation` — but the trait overrides Livewire-internal
// validate/validateOnly methods that this class never invokes, so the
// trait is dead code (collectiq dogfood, 2026-04-26). Bail when the
// class shows no Livewire-side validation surface.
class ReadingStatsPage extends Component
{
    public function recompute(array $input): void
    {
        Validator::make($input, [
            'window_days' => FluentRule::integer()->required()->min(1),
        ])->validate();
    }
}

?>
