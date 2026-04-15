<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport\InteractsWithForms;

final class PreviouslyMismatchedFilamentComponent extends Component
{
    use HasFluentValidation;
    use InteractsWithForms;

    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->required(),
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

final class PreviouslyMismatchedFilamentComponent extends Component
{
    use HasFluentValidationForFilament {
        HasFluentValidationForFilament::validate insteadof InteractsWithForms;
        HasFluentValidationForFilament::validateOnly insteadof InteractsWithForms;
        HasFluentValidationForFilament::getRules insteadof InteractsWithForms;
        HasFluentValidationForFilament::getValidationAttributes insteadof InteractsWithForms;
    }
    use InteractsWithForms;

    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->required(),
        ];
    }
}

?>
