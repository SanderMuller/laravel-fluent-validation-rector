<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureSupport\BaseWithFinalPublicRules;

final class ChildWithFinalParentRules extends BaseWithFinalPublicRules
{
    #[Rule('required|string|max:255')]
    public string $name = '';
}

?>
