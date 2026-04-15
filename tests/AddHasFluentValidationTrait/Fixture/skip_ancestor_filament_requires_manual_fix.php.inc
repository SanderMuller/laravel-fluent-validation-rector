<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\FixtureSupport\FakeFilamentBase;

final class InheritedFilamentComponent extends FakeFilamentBase
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }

    public function render()
    {
        return view('livewire.inherited-filament');
    }
}

?>
