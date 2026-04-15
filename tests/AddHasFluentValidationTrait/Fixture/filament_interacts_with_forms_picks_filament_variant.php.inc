<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport\InteractsWithForms;

final class FilamentFormComponent extends Component
{
    use InteractsWithForms;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
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

final class FilamentFormComponent extends Component
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
