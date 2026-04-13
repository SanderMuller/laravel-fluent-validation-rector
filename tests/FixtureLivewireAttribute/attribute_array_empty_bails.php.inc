<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use Livewire\Attributes\Rule;
use Livewire\Component;

final class ArrayEmptyAttributeComponent extends Component
{
    #[Rule([])]
    public string $name = '';
}

?>
