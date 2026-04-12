<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

class LivewireComponent extends Component
{
    public function rules(): array
    {
        return [
            'items' => FluentRule::array()->required(),
            'items.*.name' => FluentRule::string()->required(),
        ];
    }
}

?>
