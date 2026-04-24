<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

class SkipPasswordMinDynamicArg
{
    private int $minLength = 10;

    public function rules(): array
    {
        return [
            // Non-literal arg — Password::min($this->minLength) stays as escape hatch.
            // Promotion would lose the type-narrowing static analyzers expect
            // on the integer bound.
            'password' => FluentRule::string()->required()->rule(Password::min($this->minLength)),
        ];
    }
}

?>
