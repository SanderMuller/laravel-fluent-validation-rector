<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

// HasFluentValidation (the Livewire trait) has no schema() dispatch hook —
// only HasFluentRules does. Leave the rules() method untouched.
class SkipHasFluentValidationTrait
{
    use HasFluentValidation;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
