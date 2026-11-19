<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// array() reads its first argument as the key list, not as a label.
class SkipFieldArrayTokenWithLabelArg
{
    public function rules(): array
    {
        return [
            'tags' => FluentRule::field('Tags')->rule('array'),
        ];
    }
}

?>
