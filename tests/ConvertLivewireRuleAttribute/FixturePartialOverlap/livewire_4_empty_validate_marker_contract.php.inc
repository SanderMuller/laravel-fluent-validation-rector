<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Livewire\Attributes\Validate;
use Livewire\Component;

// **Livewire 4 runtime contract** (collectiq dogfood 2026-04-26 follow-up).
//
// Under `OVERLAP_BEHAVIOR_PARTIAL`, when a property's `#[Validate('rule')]`
// is converted to `rules()`, the rector preserves an empty `#[Validate]`
// marker on the property. Livewire's real-time validation pipeline
// (`wire:model.live`) checks for the attribute's PRESENCE — empty or not
// — to decide whether to fire validation on property updates. The
// generated `rules()` method then supplies the actual rule set.
//
// **Required Livewire 4 behavior:**
// - Empty `#[Validate]` on a property → real-time validation fires.
// - The fired validation looks up the property's rules via the class's
//   `rules()` method (not via the empty attribute's args).
// - This matches Livewire v3's documented behavior; v4's docs preserve
//   it (https://livewire.laravel.com/docs/properties#validating-properties).
//
// If a future Livewire release changes this contract (empty `#[Validate]`
// becomes a no-op or an error), this fixture and the
// `appendRealtimeValidationMarker` helper need to flip to OMIT the
// empty attribute entirely. Consumers can opt out today via the
// `preserve_realtime_validation: false` config key (see the
// `FixturePreservationDisabled` directory).
//
// The runtime check itself — booting a Livewire 4 page and confirming
// `wire:model.live` triggers validation through the empty attribute —
// must happen on the consumer side; this package has no Livewire dev
// dependency and cannot exercise the runtime path in its test suite.
final class Livewire4EmptyValidateMarkerContract extends Component
{
    #[Validate('required|string')]
    public string $title = '';

    public function save(): void
    {
        $this->validate([
            'body' => 'required|string',
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

// **Livewire 4 runtime contract** (collectiq dogfood 2026-04-26 follow-up).
//
// Under `OVERLAP_BEHAVIOR_PARTIAL`, when a property's `#[Validate('rule')]`
// is converted to `rules()`, the rector preserves an empty `#[Validate]`
// marker on the property. Livewire's real-time validation pipeline
// (`wire:model.live`) checks for the attribute's PRESENCE — empty or not
// — to decide whether to fire validation on property updates. The
// generated `rules()` method then supplies the actual rule set.
//
// **Required Livewire 4 behavior:**
// - Empty `#[Validate]` on a property → real-time validation fires.
// - The fired validation looks up the property's rules via the class's
//   `rules()` method (not via the empty attribute's args).
// - This matches Livewire v3's documented behavior; v4's docs preserve
//   it (https://livewire.laravel.com/docs/properties#validating-properties).
//
// If a future Livewire release changes this contract (empty `#[Validate]`
// becomes a no-op or an error), this fixture and the
// `appendRealtimeValidationMarker` helper need to flip to OMIT the
// empty attribute entirely. Consumers can opt out today via the
// `preserve_realtime_validation: false` config key (see the
// `FixturePreservationDisabled` directory).
//
// The runtime check itself — booting a Livewire 4 page and confirming
// `wire:model.live` triggers validation through the empty attribute —
// must happen on the consumer side; this package has no Livewire dev
// dependency and cannot exercise the runtime path in its test suite.
final class Livewire4EmptyValidateMarkerContract extends Component
{
    #[Validate]
    public string $title = '';

    public function save(): void
    {
        $this->validate([
            'body' => 'required|string',
        ]);
    }

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>
