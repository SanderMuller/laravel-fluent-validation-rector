<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use Livewire\Component;

final class ArrayEmptyAttributeComponent extends Component
{
    #[Rule([])]
    public string $name = '';
}

?>
