<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// The message binds to the constraint set just before it; removing the hop could rebind it.
class SkipFieldTypeTokenFollowedByMessage
{
    public function rules(): array
    {
        return [
            'tags' => FluentRule::field()->required()->rule('array')->message('Pick some tags.'),
        ];
    }
}

?>
