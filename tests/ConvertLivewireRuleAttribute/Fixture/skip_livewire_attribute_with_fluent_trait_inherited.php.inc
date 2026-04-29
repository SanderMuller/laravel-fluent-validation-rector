<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureSupport\BaseWithFluentValidationTrait;

// 1.2.0 fixture: the canonical mijntp shape. Concrete child has
// property-level `#[Rule]` attribute; abstract parent uses
// `HasFluentValidation`. The trait's `getRules()` reads only the
// parent's `rules(): array` method, not Livewire attribute metadata
// — `validateOnly('$phoneNumber')` silently returns `[]` at runtime.
//
// Pre-1.2.0 the rector either converted the attribute (overriding
// the parent's `rules()` and losing parent-owned fields) or fell
// through some other safety check; either way no diagnostic surfaced.
//
// Post-1.2.0 the rector emits a layer-2 skip-log entry naming the
// offending property + remediation options (move into trait's
// `rules()` OR remove the trait if Livewire-attribute validation is
// intended), and bails conversion.
//
// No `-----` separator: input == output (skip fixture). Skip-log
// emit verified separately via `LivewireSkipLogTest`.
//
// Spec reference: specs/1.2.0-livewire-fluent-trait-compose-warning.md §4.3.
final class SetupSmsTwoFactorAuthentication extends BaseWithFluentValidationTrait
{
    #[Rule(['required', 'string', 'min:10'])]
    public ?string $phoneNumber = null;
}

?>
