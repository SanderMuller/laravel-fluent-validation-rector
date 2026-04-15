<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidationForFilament;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport\InteractsWithForms;

/**
 * Pins the 0.4.15 partial-migration state: rector already inserted
 * HasFluentValidationForFilament (against main-package 1.8.0, when no
 * adaptation was needed), then main-package 1.8.1 added method overrides
 * that require an `insteadof` block. This fixture documents that the 0.4.16
 * rector leaves the class ALONE rather than retrofitting in-place — the
 * `vendor/bin/fluent-validation-migrate` source-text CLI is the supported
 * retrofit path because it also works for consumers whose classes fatal at
 * autoload (the chicken-and-egg state rector itself can't escape).
 */
final class PartialMigrationComponent extends Component
{
    use HasFluentValidationForFilament;
    use InteractsWithForms;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
