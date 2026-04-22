<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipFactoryField
{
    public function rules(): array
    {
        // field factory has no implicit constraint, so no message: param.
        return [
            'custom' => FluentRule::field()->message('Bad field.'),
        ];
    }
}

?>
