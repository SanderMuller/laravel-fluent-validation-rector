<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

// 1.2.1 Codex-review guard fixture. The pre-1.2.1-final draft of the
// per-property overlap-bail emit walked every property carrying ANY
// Livewire attribute and ran `predictEmitKeysForProperty` on it,
// including:
//
//   - Empty-marker attrs (`#[Validate]` with no args) preserved
//     post-convert for `wire:model.live` real-time validation.
//   - Named-arg-only attrs like `#[Validate(messages: [...])]` whose
//     first positional arg is absent.
//
// Both shapes ARE non-convertible. The pre-fix predict path
// fabricated the property name as a "key" and the bail-emit said:
// "Set KEY_OVERLAP_BEHAVIOR=partial to convert this property" — but
// partial mode would also leave the property as attrs (no rule to
// convert). Misdirected the consumer on a release whose value-add
// is more actionable overlap diagnostics.
//
// Fix: `predictEmitKeysForProperty` now bails when the attribute
// has no positional first arg, AND `emitOverlapBailPerProperty`
// skips properties with zero predicted keys.
//
// Pin: this fixture has `$name` (convertible, overlapping) AND
// `$bio` (empty-marker, non-convertible). The bail-emit walks both
// but emits ONLY for `$name`. The explicit-validate-call drives the
// classwide bail (under default KEY_OVERLAP_BEHAVIOR=bail).
//
// No `-----` separator: skip fixture. Skip-log emit asserted via
// `LivewireSkipLogTest::testEmptyMarkerPropertyIsNotMisreported`.
final class HybridWithEmptyMarkerPage extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate]
    public string $bio = '';

    public function saveName(): void
    {
        $this->validate([
            'name' => 'required|string|max:500',
        ]);
    }
}

?>
