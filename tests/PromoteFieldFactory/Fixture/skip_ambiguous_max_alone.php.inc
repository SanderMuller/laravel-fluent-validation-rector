<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipAmbiguousMaxAlone
{
    public function rules(): array
    {
        return [
            // `max` exists on StringRule, NumericRule, ArrayRule, FileRule — intersection is > 1.
            // No promotion. The user must narrow the type themselves.
            'value' => FluentRule::field()->rule('max:10'),
        ];
    }
}

?>
