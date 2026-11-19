<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// FluentRule::integer() also adds 'numeric', which the original does not have.
class SkipFieldIntegerTokenAlone
{
    public function rules(): array
    {
        return [
            'count' => FluentRule::field()->required()->rule('integer'),
        ];
    }
}

?>
