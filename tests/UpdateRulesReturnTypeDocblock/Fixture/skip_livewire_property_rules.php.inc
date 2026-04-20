<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Livewire\Attributes\Validate;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

class SkipLivewirePropertyRules
{
    use HasFluentValidation;

    #[Validate(FluentRule::class, 'string()->required()->max(255)')]
    public string $title = '';
}

?>
