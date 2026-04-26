<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport\InteractsWithForms;

// Filament component using `InteractsWithForms` directly. FluentRule is
// used inside a form/schema builder method (here: `formSchema()`),
// not via a local `rules()` method or `$this->validate(...)` call.
// Filament's own pipeline invokes validation through the form trait —
// `HasFluentValidationForFilament`'s overrides DO get hit. Without the
// Filament-trait-as-surface signal the gate would silently skip
// inserting the trait. Codex review (2026-04-26) catch on the 0.14.1
// branch: surfaced as a HIGH false-negative.
final class FilamentSchemaOnlyComponent extends Component
{
    use InteractsWithForms;

    public function formSchema(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidationForFilament;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport\InteractsWithForms;

// Filament component using `InteractsWithForms` directly. FluentRule is
// used inside a form/schema builder method (here: `formSchema()`),
// not via a local `rules()` method or `$this->validate(...)` call.
// Filament's own pipeline invokes validation through the form trait —
// `HasFluentValidationForFilament`'s overrides DO get hit. Without the
// Filament-trait-as-surface signal the gate would silently skip
// inserting the trait. Codex review (2026-04-26) catch on the 0.14.1
// branch: surfaced as a HIGH false-negative.
final class FilamentSchemaOnlyComponent extends Component
{
    use HasFluentValidationForFilament {
        HasFluentValidationForFilament::validate insteadof InteractsWithForms;
        HasFluentValidationForFilament::validateOnly insteadof InteractsWithForms;
        HasFluentValidationForFilament::getRules insteadof InteractsWithForms;
        HasFluentValidationForFilament::getValidationAttributes insteadof InteractsWithForms;
    }
    use InteractsWithForms;

    public function formSchema(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
