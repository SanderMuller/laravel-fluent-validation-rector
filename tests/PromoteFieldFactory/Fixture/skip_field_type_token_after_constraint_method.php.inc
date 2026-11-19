<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// in() is a constraint, not a presence modifier, so 'string' would move in front of it.
class SkipFieldTypeTokenAfterConstraintMethod
{
    public function rules(): array
    {
        return [
            'status' => FluentRule::field()->in(['draft', 'published'])->rule('string'),
        ];
    }
}

?>
