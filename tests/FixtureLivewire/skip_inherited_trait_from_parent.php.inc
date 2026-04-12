<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewire;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\FixtureSupport\FakeLivewireBaseWithTrait;

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
