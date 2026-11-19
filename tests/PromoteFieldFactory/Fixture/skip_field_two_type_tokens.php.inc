<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Two conflicting type tokens: no single typed factory matches.
class SkipFieldTwoTypeTokens
{
    public function rules(): array
    {
        return [
            'value' => FluentRule::field()->rule('string')->rule('array'),
        ];
    }
}

?>
