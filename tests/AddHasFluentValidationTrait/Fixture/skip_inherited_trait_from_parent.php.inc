<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport\FakeLivewireBaseWithTrait;

class LivewireSubclassInheritingTrait extends FakeLivewireBaseWithTrait
{
    public array $credentials = [];

    public function rules(): array
    {
        return [
            'credentials.username' => FluentRule::string()->required(),
        ];
    }

    public function render()
    {
        return 'rendered';
    }
}

?>
