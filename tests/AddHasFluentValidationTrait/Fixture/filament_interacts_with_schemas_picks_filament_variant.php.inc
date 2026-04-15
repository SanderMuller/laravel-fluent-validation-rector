<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport\InteractsWithSchemas;

final class FilamentV5Component extends Component
{
    use InteractsWithSchemas;

    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(100),
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
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport\InteractsWithSchemas;

final class FilamentV5Component extends Component
{
    use HasFluentValidationForFilament {
        HasFluentValidationForFilament::validate insteadof InteractsWithSchemas;
        HasFluentValidationForFilament::validateOnly insteadof InteractsWithSchemas;
        HasFluentValidationForFilament::getRules insteadof InteractsWithSchemas;
        HasFluentValidationForFilament::getValidationAttributes insteadof InteractsWithSchemas;
    }
    use InteractsWithSchemas;

    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(100),
        ];
    }
}

?>
