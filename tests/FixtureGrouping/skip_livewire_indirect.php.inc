<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use SanderMuller\FluentValidation\FluentRule;

class IndirectLivewireComponent extends SomeAppBaseComponent
{
    public function rules(): array
    {
        return [
            'items' => FluentRule::array()->required(),
            'items.*.name' => FluentRule::string()->required(),
        ];
    }

    public function render()
    {
        return view('foo');
    }
}

?>
