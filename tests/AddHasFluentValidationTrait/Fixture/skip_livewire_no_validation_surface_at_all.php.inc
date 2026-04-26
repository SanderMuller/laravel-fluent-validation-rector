<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

// Livewire component using FluentRule inside a private helper that
// returns a rules-shaped array — but the helper is never invoked via
// `$this->validate(...)` / `$this->validateOnly(...)`, no `#[Validate]`
// attribute, no `rules()` method. The auto-detect path may grab the
// helper as rules-shaped, but the Livewire-side validation surface is
// inert. The trait would be dead code. Bail when no validation
// surface is exercised.
class StatsWidget extends Component
{
    public function render()
    {
        return view('livewire.stats-widget');
    }

    private function exportRulesForExternalUse(): array
    {
        return [
            'days' => FluentRule::integer()->required()->min(1),
        ];
    }
}

?>
